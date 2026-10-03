#!/usr/bin/env bash
#
# playout-smoke-test.sh — Linux-only end-to-end check of the two-stage
# "My Channel" playout (rigid normalisation -> Stage 1 `-c copy` -> FIFO ->
# Stage 2 encode/overlays -> disk HLS).
#
# Run on the server as any user that can read the app directory:
#
#     ./deploy/playout-smoke-test.sh [channel-slug]
#
# Exits 0 when every check passes, 1 on a failed check, 2 when the host
# cannot run the test (non-Linux, required tools or the app missing). Never touches
# an existing channel: it creates its own throwaway slug and deletes it.
#
set -uo pipefail

SLUG="${1:-smoke-$$}"
SLUG="${SLUG//[^A-Za-z0-9_.-]/_}"
SEGMENTS_WANTED=8
SETTLE_SECONDS=90

say()  { printf '  %s\n' "$*"; }
head_() { printf '\n%s\n' "$*"; }
PASS=0
FAIL=0
pass() { PASS=$((PASS + 1)); say "PASS  $*"; }
fail() { FAIL=$((FAIL + 1)); say "FAIL  $*"; }
skip() { say "SKIP  $*"; }

# ── 1. Host guards ──────────────────────────────────────────────────────────
[ "$(uname -s)" = "Linux" ] || { skip "not Linux ($(uname -s)) — smoke test is Linux-only"; exit 2; }
command -v ffmpeg  >/dev/null 2>&1 || { skip "ffmpeg not on PATH"; exit 2; }
command -v ffprobe >/dev/null 2>&1 || { skip "ffprobe not on PATH"; exit 2; }
command -v php     >/dev/null 2>&1 || { skip "php not on PATH"; exit 2; }
command -v python3 >/dev/null 2>&1 || { skip "python3 not on PATH"; exit 2; }
[ -f artisan ] || { skip "run from the application root (no artisan found)"; exit 2; }

APP="$(pwd)"
STREAM_DIR="$APP/storage/app/streams/hls/admin-channel-$SLUG"
RAM_DIR="/dev/shm/studio/$SLUG"
WORK="$(mktemp -d /tmp/playout-smoke.XXXXXX)"
SUPERVISOR_PID=""
CLEANED=0

# Refuse to run against anything that already exists — this script deletes
# the channel directory it works in.
if [ -e "$STREAM_DIR" ] || [ -e "$RAM_DIR" ]; then
    echo "refusing: $STREAM_DIR (or its ramdir) already exists" >&2
    echo "pick a different slug, or remove the leftover directory first" >&2
    rm -rf "$WORK"
    exit 2
fi

cleanup() {
    [ "$CLEANED" = "1" ] && return
    CLEANED=1
    if [ -n "$SUPERVISOR_PID" ]; then
        kill -TERM "$SUPERVISOR_PID" 2>/dev/null
        wait "$SUPERVISOR_PID" 2>/dev/null
    fi
    pkill -TERM -f "$STREAM_DIR/playout.pipe" 2>/dev/null
    rm -rf "$WORK" "$RAM_DIR"
    # Only ever removes the channel this script created.
    rm -rf "$STREAM_DIR"
}
trap cleanup EXIT INT TERM

head_ "== playout smoke test: $SLUG =="

# ── 2. Five clips, including a one-second item ──────────────────────────────
head_ "[1/10] generating source clips"
COLORS=(red green blue yellow magenta)
FREQUENCIES=(440 550 660 770 880)
for i in "${!COLORS[@]}"; do
    n=$((i + 1))
    duration=3
    [ "$n" -eq 5 ] && duration=1
    ffmpeg -y -hide_banner -loglevel error \
        -f lavfi -i "color=c=${COLORS[$i]}:size=640x360:rate=25:duration=$duration" \
        -f lavfi -i "sine=frequency=${FREQUENCIES[$i]}:sample_rate=48000:duration=$duration" \
        -c:v libx264 -preset ultrafast -pix_fmt yuv420p -g 50 \
        -c:a aac -b:a 96k \
        "$WORK/clip$n.mp4" || { fail "could not generate clip$n"; exit 1; }
done
say "ok: $(ls -1 "$WORK"/clip*.mp4 | wc -l) distinct colour clips, including a one-second clip"

# ── 3. Render the real playout scripts through the real service ─────────────
head_ "[2/10] rendering playout.sh + stage2.sh via MyChannelHlsService"
php >"$WORK/render.out" 2>"$WORK/render.err" <<PHP
<?php
require '$APP/vendor/autoload.php';
\$app = require '$APP/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\$ch = new App\Models\AdminChannel\AdminChannel([
    'channel_slug'          => '$SLUG',
    'channel_name'          => 'Playout smoke test',
    'output_resolution'     => '640x360',
    'output_frame_rate'     => 25,
    'output_bitrate'        => 1200,
    'enable_ticker'         => true,
    'ticker_text'           => 'SMOKE TEST TICKER',
    'enable_overlay_clock'  => true,
    'overlay_clock_format'  => 'HH:MM:SS',
    'enable_overlay_logo'   => false,
    'enable_watermark'      => false,
]);

\$svc = new App\Services\AdminChannel\MyChannelHlsService();

function call(\$svc, \$name, ...\$args) {
    \$m = new ReflectionMethod(App\Services\AdminChannel\MyChannelHlsService::class, \$name);
    \$m->setAccessible(true);

    return \$m->invoke(\$svc, ...\$args);
}

\$dir = '$STREAM_DIR';
@mkdir(\$dir, 0775, true);
@mkdir('$RAM_DIR', 0755, true);

call(\$svc, 'writeOverlayAssets', \$dir, \$ch);
echo call(\$svc, 'writePlayoutScript', \$dir, [
    '$WORK/clip1.mp4', '$WORK/clip2.mp4', '$WORK/clip3.mp4', '$WORK/clip4.mp4', '$WORK/clip5.mp4',
], \$ch, 'png'), "\n";
PHP
RENDER_RC=$?
if [ $RENDER_RC -ne 0 ]; then
    fail "script render failed (rc=$RENDER_RC)"
    sed 's/^/        /' "$WORK/render.err" | tail -20
    exit 1
fi
SCRIPT="$(cat "$WORK/render.out" | tail -1)"
[ -f "$SCRIPT" ] && [ -f "$STREAM_DIR/stage2.sh" ] || { fail "scripts not written"; exit 1; }

for f in "$SCRIPT" "$STREAM_DIR/stage2.sh"; do
    if bash -n "$f" 2>"$WORK/bashn.err"; then
        pass "bash -n $(basename "$f")"
    else
        fail "bash -n $(basename "$f"): $(head -3 "$WORK/bashn.err")"
    fi
done

# ── 4. Start the supervisor ─────────────────────────────────────────────────
head_ "[3/10] starting supervisor"
bash "$SCRIPT" >"$WORK/supervisor.out" 2>&1 &
SUPERVISOR_PID=$!
say "supervisor pid=$SUPERVISOR_PID"

deadline=$(( $(date +%s) + 45 ))
while [ "$(date +%s)" -lt "$deadline" ]; do
    [ -f "$STREAM_DIR/stage1.pid" ] && [ -f "$STREAM_DIR/stage2.pid" ] && break
    kill -0 "$SUPERVISOR_PID" 2>/dev/null || { fail "supervisor died during startup"; break; }
    sleep 1
done

if kill -0 "$SUPERVISOR_PID" 2>/dev/null; then
    pass "supervisor alive"
else
    fail "supervisor not running"
fi

if [ -f "$STREAM_DIR/stage1.pid" ]; then
    S1=$(cat "$STREAM_DIR/stage1.pid")
    kill -0 "$S1" 2>/dev/null && pass "stage1 loop alive (pid $S1)" || fail "stage1 loop dead (pid $S1)"
else
    fail "stage1.pid missing"; S1=""
fi

if [ -f "$STREAM_DIR/stage2.pid" ]; then
    S2=$(cat "$STREAM_DIR/stage2.pid")
    kill -0 "$S2" 2>/dev/null && pass "stage2 encoder alive (pid $S2)" || fail "stage2 encoder dead (pid $S2)"
else
    fail "stage2.pid missing"; S2=""
fi

# ── 5. Segments actually appear ─────────────────────────────────────────────
head_ "[4/10] waiting for HLS output (up to ${SETTLE_SECONDS}s)"
deadline=$(( $(date +%s) + SETTLE_SECONDS ))
while [ "$(date +%s)" -lt "$deadline" ]; do
    count=$(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | wc -l)
    [ "$count" -ge "$SEGMENTS_WANTED" ] && break
    sleep 2
done
count=$(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | wc -l)
if [ -f "$STREAM_DIR/index.m3u8" ] && [ "$count" -ge "$SEGMENTS_WANTED" ]; then
    pass "produced $count segments + index.m3u8"
else
    fail "only $count segments (wanted $SEGMENTS_WANTED), index.m3u8 $([ -f "$STREAM_DIR/index.m3u8" ] && echo present || echo missing)"
    tail -20 "$STREAM_DIR/ffmpeg.log" 2>/dev/null | sed 's/^/        /'
fi

# ── Verify every playlist entry reaches the encoded output ─────────────────
head_ "[5/10] checking all five clips, including the one-second clip"
python3 - "$STREAM_DIR" <<'PY'
import glob
import pathlib
import subprocess
import sys

stream_dir = pathlib.Path(sys.argv[1])
segments = sorted(glob.glob(str(stream_dir / "seg_*.ts")))
seen = set()

for segment in segments:
    decoded = subprocess.run(
        [
            "ffmpeg", "-hide_banner", "-loglevel", "error", "-i", segment,
            "-vf", "fps=10,crop=2:2:(iw-2)/2:(ih-2)/2,format=rgb24",
            "-f", "rawvideo", "-",
        ],
        stdout=subprocess.PIPE,
        stderr=subprocess.DEVNULL,
        check=False,
    )
    if decoded.returncode != 0 or len(decoded.stdout) < 12:
        continue

    for offset in range(0, len(decoded.stdout) - 11, 12):
        pixels = [decoded.stdout[offset + i:offset + i + 3] for i in range(0, 12, 3)]
        red = sum(pixel[0] for pixel in pixels) / 4
        green = sum(pixel[1] for pixel in pixels) / 4
        blue = sum(pixel[2] for pixel in pixels) / 4

        if red > green * 1.5 and red > blue * 1.5:
            seen.add("red")
        elif green > red * 1.5 and green > blue * 1.5:
            seen.add("green")
        elif blue > red * 1.5 and blue > green * 1.5:
            seen.add("blue")
        elif red > 120 and green > 120 and blue < 100:
            seen.add("yellow")
        elif red > 120 and blue > 120 and green < 100:
            seen.add("magenta")

missing = {"red", "green", "blue", "yellow", "magenta"} - seen
if missing:
    print(f"FAIL: missing clips in HLS output: {', '.join(sorted(missing))}")
    sys.exit(1)

print("PASS: all five playlist clips, including the one-second clip, decoded from HLS segments")
PY
if [ "$?" -eq 0 ]; then
    pass "all five ordered playlist clips appear in the HLS segments"
else
    fail "one or more playlist clips never reached the HLS output"
fi

# ── 6. Playlist change: coordinated reload must preserve HLS continuity ────
head_ "[6/10] playlist update continuity"
S1_FFMPEG_BEFORE=$(cat "$STREAM_DIR/stage1.ffmpeg.pid" 2>/dev/null || echo "")
S2_BEFORE=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
SEQ_BEFORE=$(awk -F: '/^#EXT-X-MEDIA-SEQUENCE:/{print $2; exit}' "$STREAM_DIR/index.m3u8" 2>/dev/null)
python3 - "$STREAM_DIR/concat.txt" <<'PY'
import pathlib
import sys

concat = pathlib.Path(sys.argv[1])
entries = concat.read_text().splitlines()
swap = concat.with_name(concat.name + ".switch")
swap.write_text("".join(f"{entry}\n" for entry in reversed(entries)))
swap.replace(concat)
PY
touch "$STREAM_DIR/.reload-stage1"
deadline=$(( $(date +%s) + 45 ))
S1_FFMPEG_AFTER=""
S2_AFTER=""
SEQ_AFTER=""
while [ "$(date +%s)" -lt "$deadline" ]; do
    S1_FFMPEG_AFTER=$(cat "$STREAM_DIR/stage1.ffmpeg.pid" 2>/dev/null || echo "")
    S2_AFTER=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
    SEQ_AFTER=$(awk -F: '/^#EXT-X-MEDIA-SEQUENCE:/{print $2; exit}' "$STREAM_DIR/index.m3u8" 2>/dev/null)
    if [ -n "$S1_FFMPEG_AFTER" ] && [ -n "$S2_AFTER" ] \
       && [ "$S1_FFMPEG_AFTER" != "$S1_FFMPEG_BEFORE" ] \
       && [ "$S2_AFTER" != "$S2_BEFORE" ] \
       && [ -n "$SEQ_BEFORE" ] && [ -n "$SEQ_AFTER" ] \
       && [ "$SEQ_AFTER" -gt "$SEQ_BEFORE" ]; then
        break
    fi
    sleep 1
done
if [ -n "$S1_FFMPEG_AFTER" ] && [ -n "$S2_AFTER" ] \
   && [ "$S1_FFMPEG_AFTER" != "$S1_FFMPEG_BEFORE" ] \
   && [ "$S2_AFTER" != "$S2_BEFORE" ] \
   && [ -n "$SEQ_BEFORE" ] && [ -n "$SEQ_AFTER" ] \
   && [ "$SEQ_AFTER" -gt "$SEQ_BEFORE" ] \
   && ! grep -q '^#EXT-X-ENDLIST' "$STREAM_DIR/index.m3u8"; then
    pass "playlist swap restarted both stages and kept HLS sequence advancing ($SEQ_BEFORE -> $SEQ_AFTER)"
else
    fail "playlist swap interrupted HLS or did not restart both stages"
    tail -30 "$STREAM_DIR/ffmpeg.log" 2>/dev/null | sed 's/^/        /'
fi

# ── 7. Canvas update: coordinated restart keeps the live item on top ────────
head_ "[7/10] canvas update and coordinated reload"
S2_BEFORE=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
if [ -n "$S2_BEFORE" ] && [ -f "$RAM_DIR/overlay.png" ]; then
    S1_LOOP_BEFORE=$(cat "$STREAM_DIR/stage1.pid" 2>/dev/null || echo "")
    S1_FFMPEG_BEFORE=$(cat "$STREAM_DIR/stage1.ffmpeg.pid" 2>/dev/null || echo "")
    # A visibly different canvas: 200x200 solid magenta.
    ffmpeg -y -hide_banner -loglevel error \
        -f lavfi -i "color=c=0xFF00FF:s=200x200" \
        -frames:v 1 -c:v png "$RAM_DIR/overlay.tmp.png" 2>/dev/null
    if [ -f "$RAM_DIR/overlay.tmp.png" ]; then
        mv -f "$RAM_DIR/overlay.tmp.png" "$RAM_DIR/overlay.png"
        # image2 -loop 1 holds its decoded packet. The production update path
        # coordinates Stage 1 and Stage 2 at a fresh NUT header.
        touch "$STREAM_DIR/.reload-stage1"
        deadline=$(( $(date +%s) + 45 ))
        S2_AFTER=""
        S1_FFMPEG_AFTER=""
        while [ "$(date +%s)" -lt "$deadline" ]; do
            S2_AFTER=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
            S1_FFMPEG_AFTER=$(cat "$STREAM_DIR/stage1.ffmpeg.pid" 2>/dev/null || echo "")
            [ -n "$S2_AFTER" ] && [ "$S2_AFTER" != "$S2_BEFORE" ] \
                && [ -n "$S1_FFMPEG_AFTER" ] && [ "$S1_FFMPEG_AFTER" != "$S1_FFMPEG_BEFORE" ] \
                && kill -0 "$S2_AFTER" 2>/dev/null && break
            sleep 1
        done
        S1_LOOP_AFTER=$(cat "$STREAM_DIR/stage1.pid" 2>/dev/null || echo "")
        if [ -n "$S2_AFTER" ] && [ "$S2_AFTER" != "$S2_BEFORE" ] && kill -0 "$S2_AFTER" 2>/dev/null; then
            pass "encoder relaunched at a fresh NUT header ($S2_BEFORE -> $S2_AFTER)"
        else
            fail "encoder did not relaunch for updated canvas"
        fi
        if [ -n "$S1_LOOP_BEFORE" ] && [ "$S1_LOOP_AFTER" = "$S1_LOOP_BEFORE" ] \
           && [ -n "$S1_FFMPEG_AFTER" ] && [ "$S1_FFMPEG_AFTER" != "$S1_FFMPEG_BEFORE" ] \
           && kill -0 "$S1_LOOP_AFTER" 2>/dev/null; then
            pass "supervisor preserved playlist loop and relaunched its producer ($S1_FFMPEG_BEFORE -> $S1_FFMPEG_AFTER)"
        else
            fail "coordinated Stage 1 producer reload failed ($S1_FFMPEG_BEFORE -> $S1_FFMPEG_AFTER)"
        fi
        sleep 5
        mapfile -t RECENT_SEGS < <(ls -1t "$STREAM_DIR"/seg_*.ts 2>/dev/null | head -5)
        if [ "${#RECENT_SEGS[@]}" -gt 0 ] && python3 - "${RECENT_SEGS[@]}" <<'PY'
import subprocess
import sys

for segment in sys.argv[1:]:
    frame = subprocess.run(
        [
            "ffmpeg", "-hide_banner", "-loglevel", "error", "-i", segment,
            "-ss", "0.5", "-frames:v", "1",
            "-vf", "crop=2:2:100:100,format=rgb24", "-f", "rawvideo", "-",
        ],
        stdout=subprocess.PIPE,
        stderr=subprocess.DEVNULL,
        check=False,
    ).stdout
    if len(frame) < 12:
        continue

    pixels = [frame[i:i + 3] for i in range(0, 12, 3)]
    red = sum(pixel[0] for pixel in pixels) / 4
    green = sum(pixel[1] for pixel in pixels) / 4
    blue = sum(pixel[2] for pixel in pixels) / 4
    if red > 150 and green < 100 and blue > 150:
        sys.exit(0)

sys.exit(1)
PY
        then
            pass "updated canvas is visible in recent HLS segments"
        else
            fail "updated canvas did not appear in recent HLS segments"
        fi
        SEGMENT_BEFORE=$(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | sort | tail -1)
        sleep 5
        SEGMENT_AFTER=$(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | sort | tail -1)
        if [ -n "$SEGMENT_BEFORE" ] && [ -n "$SEGMENT_AFTER" ] && [ "$SEGMENT_AFTER" != "$SEGMENT_BEFORE" ]; then
            pass "new HLS segments continued through canvas update ($(basename "$SEGMENT_BEFORE") -> $(basename "$SEGMENT_AFTER"))"
        else
            fail "HLS segments stalled during canvas update ($(basename "$SEGMENT_BEFORE") -> $(basename "$SEGMENT_AFTER"))"
        fi
    else
        skip "could not render a replacement canvas"
    fi
else
    skip "no overlay.png to rewrite"
fi

# ── 8. Coordinated stage restart: loop supervisor and HLS must survive ──────
head_ "[8/10] coordinated encoder recovery"
S1_LOOP_BEFORE=$(cat "$STREAM_DIR/stage1.pid" 2>/dev/null || echo "")
S1_FFMPEG_BEFORE=$(cat "$STREAM_DIR/stage1.ffmpeg.pid" 2>/dev/null || echo "")
S2_BEFORE=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
SEGMENT_BEFORE=$(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | sort | tail -1)
if [ -n "$S2_BEFORE" ] && [ -n "$S1_FFMPEG_BEFORE" ]; then
    touch "$STREAM_DIR/.reload-stage1"
    deadline=$(( $(date +%s) + 45 ))
    S2_AFTER=""
    S1_FFMPEG_AFTER=""
    while [ "$(date +%s)" -lt "$deadline" ]; do
        S2_AFTER=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
        S1_FFMPEG_AFTER=$(cat "$STREAM_DIR/stage1.ffmpeg.pid" 2>/dev/null || echo "")
        [ -n "$S2_AFTER" ] && [ "$S2_AFTER" != "$S2_BEFORE" ] \
            && [ -n "$S1_FFMPEG_AFTER" ] && [ "$S1_FFMPEG_AFTER" != "$S1_FFMPEG_BEFORE" ] \
            && kill -0 "$S2_AFTER" 2>/dev/null && break
        sleep 1
    done
    if [ -n "$S2_AFTER" ] && [ "$S2_AFTER" != "$S2_BEFORE" ]; then
        pass "stage2 relaunched at the new stream header ($S2_BEFORE -> $S2_AFTER)"
    else
        fail "stage2 did not relaunch after coordinated signal"
    fi
    S1_LOOP_AFTER=$(cat "$STREAM_DIR/stage1.pid" 2>/dev/null || echo "")
    if [ -n "$S1_LOOP_AFTER" ] && [ "$S1_LOOP_AFTER" = "$S1_LOOP_BEFORE" ] \
       && [ -n "$S1_FFMPEG_AFTER" ] && [ "$S1_FFMPEG_AFTER" != "$S1_FFMPEG_BEFORE" ] \
       && kill -0 "$S1_LOOP_AFTER" 2>/dev/null; then
        pass "stage1 supervisor stayed alive and producer relaunched ($S1_FFMPEG_BEFORE -> $S1_FFMPEG_AFTER)"
    else
        fail "stage1 coordinated reload failed ($S1_FFMPEG_BEFORE -> $S1_FFMPEG_AFTER)"
    fi
    sleep 5
    SEGMENT_AFTER=$(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | sort | tail -1)
    if [ -n "$SEGMENT_BEFORE" ] && [ -n "$SEGMENT_AFTER" ] && [ "$SEGMENT_AFTER" != "$SEGMENT_BEFORE" ]; then
        pass "HLS segments continued through coordinated reload ($(basename "$SEGMENT_BEFORE") -> $(basename "$SEGMENT_AFTER"))"
    else
        fail "HLS output stalled after coordinated reload"
    fi
else
    skip "no stage pids for coordinated reload"
fi

# ── 9. Delivery contract: monotonic PTS, sane clock timebase ────────────────
head_ "[9/10] segment timeline"
if python3 - "$STREAM_DIR/index.m3u8" <<'PY'
import pathlib
import subprocess
import sys

manifest = pathlib.Path(sys.argv[1])
lines = manifest.read_text().splitlines()
segments = []
discontinuity = False
for line in lines:
    if line == "#EXT-X-DISCONTINUITY":
        discontinuity = True
    elif line.endswith(".ts"):
        segments.append((manifest.parent / line, discontinuity))
        discontinuity = False

for (first, _), (second, has_discontinuity) in zip(segments, segments[1:]):
    if has_discontinuity:
        continue

    end = subprocess.run(
        ["ffprobe", "-v", "error", "-select_streams", "v:0",
         "-show_entries", "packet=pts_time", "-of", "csv=p=0", str(first)],
        capture_output=True, text=True, check=False,
    )
    start = subprocess.run(
        ["ffprobe", "-v", "error", "-select_streams", "v:0",
         "-show_entries", "packet=pts_time", "-of", "csv=p=0", str(second)],
        capture_output=True, text=True, check=False,
    )
    try:
        a_end = float(end.stdout.splitlines()[-1])
        b_start = float(start.stdout.splitlines()[0])
    except (IndexError, ValueError):
        continue
    if b_start < a_end - 1.0:
        print(f"PTS regression within continuous HLS timeline: {first.name} -> {second.name}")
        sys.exit(1)
PY
then
    pass "packet PTS never runs backwards across continuous HLS segments"
else
    fail "PTS regression between continuous HLS segments"
fi

mapfile -t SEGS < <(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | sort)
if [ "${#SEGS[@]}" -ge 2 ]; then
    dur=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "${SEGS[0]}" 2>/dev/null | head -1)
    if [ -n "$dur" ] && awk -v d="$dur" 'BEGIN{exit !(d > 0.5 && d < 30)}'; then
        pass "segment duration sane (${dur}s)"
    else
        fail "segment duration implausible (${dur:-none}s)"
    fi
else
    fail "fewer than 2 segments to probe"
fi

tb=$(ffprobe -v error -select_streams v:0 -show_entries stream=time_base -of csv=p=0 "${SEGS[0]}" 2>/dev/null | head -1)
case "$tb" in
    1/90000|"") [ "$tb" = "1/90000" ] && pass "video time_base is 1/90000" || skip "time_base probe unavailable" ;;
    *)          fail "video time_base is $tb (expected 1/90000)" ;;
esac

# ── 10. systemd resource clamping (optional) ────────────────────────────────
head_ "[10/10] systemd unit limits"
UNIT="iptv-playout@$SLUG.service"
if command -v systemctl >/dev/null 2>&1 && systemctl list-unit-files "$UNIT" >/dev/null 2>&1 \
   && [ "$(systemctl is-enabled "$UNIT" 2>/dev/null || echo none)" != "none" ]; then
    quota=$(systemctl show -p CPUQuota --value "$UNIT" 2>/dev/null)
    mem=$(systemctl show -p MemoryMax --value "$UNIT" 2>/dev/null)
    [ -n "$quota" ] && [ "$quota" != "" ] && pass "CPUQuota=$quota" || fail "CPUQuota not set on $UNIT"
    [ -n "$mem" ] && pass "MemoryMax=$mem" || fail "MemoryMax not set on $UNIT"
else
    skip "unit $UNIT not installed/enabled on this host"
fi

# ── Summary ─────────────────────────────────────────────────────────────────
printf '\n'
if [ "$FAIL" -eq 0 ]; then
    printf 'OK — %d checks passed\n' "$PASS"
    exit 0
fi
printf 'FAILED — %d passed, %d failed\n' "$PASS" "$FAIL"
exit 1
