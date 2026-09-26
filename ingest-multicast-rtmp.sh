#!/bin/bash
# IPTV Multicast Ingest Supervisor — RTMP Architecture
# FFmpeg reads multicast UDP → pushes to nginx RTMP (port 6000)
# nginx-rtmp generates HLS on disk → served to players on port 6001
# No PHP in segment delivery path — zero buffering.
set -uo pipefail

MW=/home/kotelhms/middleware
PIDDIR="$MW/storage/app/multicast"
LOGDIR="$MW/storage/logs/multicast"
NIC_ADDR=192.168.101.254
BUF=33554432
TIMEOUT=60000000
NICE=5
RTMP_URL="rtmp://127.0.0.1:6000/live"

mkdir -p "$PIDDIR" "$LOGDIR"

start_reader() {
    local src="$1"; shift
    local gid=$(echo -n "$src" | md5sum | cut -d' ' -f1)
    local pidf="$PIDDIR/${gid}_0.pid"
    local logf="$LOGDIR/group_${gid}.log"

    # Skip if already running
    if [ -f "$pidf" ]; then
        local pid=$(cat "$pidf" 2>/dev/null || echo 0)
        [ -d "/proc/$pid" ] && return 0
        rm -f "$pidf"
    fi

    # Build RTMP output args — one output per channel
    local outs=""
    for pair in "$@"; do
        local chid="${pair%%:*}"
        local prog="${pair##*:}"
        outs="$outs -map 0:p:${prog} -map_chapters -1 -ignore_unknown -c:v copy -c:a copy -f flv ${RTMP_URL}/${chid}"
    done

    # Write wrapper script
    local tmpf="/tmp/mcast_reader_${gid}.sh"
    cat > "$tmpf" <<WRAPPER
#!/bin/bash
echo \$\$ > $pidf
trap 'exit' TERM
while true; do
nice -n $NICE ffmpeg -threads 0 \
  -fflags +genpts+discardcorrupt+igndts+nobuffer -flags low_delay \
  -err_detect ignore_err -avoid_negative_ts make_zero -max_interleave_delta 0 \
  -flush_packets 1 -probesize 1M -analyzeduration 500000 \
  -rw_timeout $TIMEOUT -timeout $TIMEOUT \
  -i '${src}?localaddr=${NIC_ADDR}&buffer_size=${BUF}' \
  $outs \
  2>>$logf
sleep 3
done
WRAPPER
    chmod +x "$tmpf"
    setsid bash "$tmpf" </dev/null >/dev/null 2>&1 &

    sleep 0.3
    if [ -f "$pidf" ]; then
        local pid=$(cat "$pidf")
        echo "OK $src PID=$pid ch=(${*// /,})"
        return 0
    else
        echo "FAIL $src"
        return 1
    fi
}

echo "Starting multicast RTMP ingest..."

start_reader "udp://@224.101.17.1:2001" "762:10" "742:44"
start_reader "udp://@224.101.17.2:2002" "728:124" "772:125" "765:127" "773:648"
start_reader "udp://@224.101.17.5:2005" "755:276" "756:279"
start_reader "udp://@224.101.17.6:2006" "722:45" "723:46" "724:802"
start_reader "udp://@224.101.17.9:2009" "651:131"
start_reader "udp://@224.2.2.1:5016" "537:101"
start_reader "udp://@224.2.2.2:5018" "538:102"
start_reader "udp://@224.2.2.3:5020" "539:103"
start_reader "udp://@224.2.2.4:5022" "540:104"
start_reader "udp://@224.2.2.5:5024" "541:105"
start_reader "udp://@224.2.2.6:5026" "542:106"
start_reader "udp://@224.2.2.7:5028" "614:107"
start_reader "udp://@224.2.2.8:5030" "615:108"
start_reader "udp://@224.3.3.1:3016" "616:101"
start_reader "udp://@224.3.3.2:3018" "617:102"
start_reader "udp://@224.3.3.3:3020" "618:103"
start_reader "udp://@224.3.3.4:3022" "619:104"

echo "=== All readers started ==="

# Supervisor mode
if [ "${1:-}" = "supervisor" ]; then
    echo "Supervisor running (health check every 30s)..."
    while true; do
        sleep 30
        respawned=0
        NOW=$(date +%s)
        for pidf in "$PIDDIR"/*.pid; do
            [ -f "$pidf" ] || continue
            pid=$(cat "$pidf" 2>/dev/null || echo 0)
            gid=$(basename "$pidf" _0.pid)
            needs_restart=false

            if [ ! -d "/proc/$pid" ]; then
                needs_restart=true
            fi

            if [ "$needs_restart" = true ]; then
                rm -f "$pidf"
                [ -d "/proc/$pid" ] && kill -KILL "$pid" 2>/dev/null
                tmpf="/tmp/mcast_reader_${gid}.sh"
                [ -f "$tmpf" ] && setsid bash "$tmpf" </dev/null >/dev/null 2>&1 &
                ((respawned++))
            fi
        done
        [ "$respawned" -gt 0 ] && echo "[$(date '+%H:%M:%S')] Respawned $respawned dead readers"
    done
fi
