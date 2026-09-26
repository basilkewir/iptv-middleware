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
# cannot run the test (non-Linux, ffmpeg or the app missing). Never touches
# an existing channel: it creates its own throwaway slug and deletes it.
#
set -uo pipefail

SLUG="${1:-smoke-$$}"
SLUG="${SLUG//[^A-Za-z0-9_.-]/_}"
SEGMENTS_WANTED=6
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

# ── 2. Two synthetic clips (same geometry — Stage 1 only ever `-c copy`s) ───
head_ "[1/8] generating source clips"
for n in 1 2; do
    ffmpeg -y -hide_banner -loglevel error \
        -f lavfi -i "testsrc2=size=640x360:rate=25:duration=3" \
        -f lavfi -i "sine=frequency=440:sample_rate=48000:duration=3" \
        -c:v libx264 -preset ultrafast -pix_fmt yuv420p -g 50 \
        -c:a aac -b:a 96k \
        "$WORK/clip$n.mp4" || { fail "could not generate clip$n"; exit 1; }
done
say "ok: $(ls -1 "$WORK"/clip*.mp4 | wc -l) clips"

# ── 3. Render the real playout scripts through the real service ─────────────
head_ "[2/8] rendering playout.sh + stage2.sh via MyChannelHlsService"
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
echo call(\$svc, 'writePlayoutScript', \$dir, ['$WORK/clip1.mp4', '$WORK/clip2.mp4'], \$ch, 'png'), "\n";
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
head_ "[3/8] starting supervisor"
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
head_ "[4/8] waiting for HLS output (up to ${SETTLE_SECONDS}s)"
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

# ── 6. Overlay hot-reload: rewrite the canvas, encoder must not restart ─────
head_ "[5/8] overlay hot-reload"
S2_BEFORE=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
if [ -n "$S2_BEFORE" ] && [ -f "$RAM_DIR/overlay.png" ]; then
    # A visibly different canvas: 200x200 solid magenta.
    ffmpeg -y -hide_banner -loglevel error \
        -f lavfi -i "color=c=0xFF00FF:s=200x200" \
        -frames:v 1 -c:v png "$RAM_DIR/overlay.tmp.png" 2>/dev/null
    if [ -f "$RAM_DIR/overlay.tmp.png" ]; then
        mv -f "$RAM_DIR/overlay.tmp.png" "$RAM_DIR/overlay.png"
        sleep 6
        S2_AFTER=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
        if [ "$S2_AFTER" = "$S2_BEFORE" ] && kill -0 "$S2_AFTER" 2>/dev/null; then
            pass "canvas rewritten, encoder pid unchanged ($S2_AFTER)"
        else
            fail "encoder restarted on canvas rewrite ($S2_BEFORE -> $S2_AFTER)"
        fi
        count=$(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | wc -l)
        sleep 8
        count2=$(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | wc -l)
        [ "$count2" -gt "$count" ] && pass "stream continued after rewrite ($count -> $count2 segments)" \
                                   || fail "stream stalled after rewrite ($count -> $count2 segments)"
    else
        skip "could not render a replacement canvas"
    fi
else
    skip "no overlay.png to rewrite"
fi

# ── 7. Stage-2-only restart: Stage 1 must survive ───────────────────────────
head_ "[6/8] stage2-only restart isolation"
S1_BEFORE=$(cat "$STREAM_DIR/stage1.pid" 2>/dev/null || echo "")
S2_BEFORE=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
if [ -n "$S2_BEFORE" ]; then
    kill -TERM "$S2_BEFORE" 2>/dev/null
    deadline=$(( $(date +%s) + 45 ))
    S2_AFTER=""
    while [ "$(date +%s)" -lt "$deadline" ]; do
        S2_AFTER=$(cat "$STREAM_DIR/stage2.pid" 2>/dev/null || echo "")
        [ -n "$S2_AFTER" ] && [ "$S2_AFTER" != "$S2_BEFORE" ] && kill -0 "$S2_AFTER" 2>/dev/null && break
        sleep 1
    done
    if [ -n "$S2_AFTER" ] && [ "$S2_AFTER" != "$S2_BEFORE" ]; then
        pass "stage2 relaunched ($S2_BEFORE -> $S2_AFTER)"
    else
        fail "stage2 did not relaunch"
    fi
    S1_AFTER=$(cat "$STREAM_DIR/stage1.pid" 2>/dev/null || echo "")
    if [ -n "$S1_AFTER" ] && [ "$S1_AFTER" = "$S1_BEFORE" ] && kill -0 "$S1_AFTER" 2>/dev/null; then
        pass "stage1 untouched by the encoder restart (pid $S1_AFTER)"
    else
        fail "stage1 was disturbed ($S1_BEFORE -> $S1_AFTER)"
    fi
else
    skip "no stage2 pid"
fi

# ── 8. Delivery contract: monotonic PTS, sane clock timebase ────────────────
head_ "[7/8] segment timeline"
mapfile -t SEGS < <(ls -1 "$STREAM_DIR"/seg_*.ts 2>/dev/null | sort)
if [ "${#SEGS[@]}" -ge 2 ]; then
    monotonic=1
    for ((i = 0; i < ${#SEGS[@]} - 1 && i < 8; i++)); do
        a_end=$(ffprobe -v error -select_streams v:0 -show_entries packet=pts_time -of csv=p=0 "${SEGS[$i]}" 2>/dev/null | tail -1)
        b_start=$(ffprobe -v error -select_streams v:0 -show_entries packet=pts_time -of csv=p=0 "${SEGS[$((i + 1))]}" 2>/dev/null | head -1)
        [ -z "$a_end" ] && continue
        [ -z "$b_start" ] && continue
        awk -v a="$a_end" -v b="$b_start" 'BEGIN{exit !(b < a - 1.0)}' && monotonic=0
    done
    [ "$monotonic" = "1" ] && pass "packet PTS never runs backwards across adjacent segments" \
                           || fail "PTS regression between adjacent segments"

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

# ── 9. systemd resource clamping (optional) ─────────────────────────────────
head_ "[8/8] systemd unit limits"
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
