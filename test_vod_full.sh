#!/bin/bash
BASE="http://localhost:25460"
COOKIES="/tmp/vod_full_test_cookies.txt"
rm -f "$COOKIES"
PASS=0; FAIL=0

ok()    { echo "  PASS: $1"; PASS=$((PASS+1)); }
fail()  { echo "  FAIL: $1 -- $2"; FAIL=$((FAIL+1)); }
check() {
  local code="$1" want="$2" label="$3"
  if [ "$code" = "$want" ]; then ok "$label ($code)"
  else fail "$label" "HTTP $code (expected $want)"; fi
}

# Extract Inertia props from HTML data-page attribute
inertia_props() {
  python3 -c "
import sys,html,json
raw=sys.stdin.read()
import re
m=re.search(r'data-page=\"([^\"]+)\"',raw)
if not m: print('{}'); exit()
d=json.loads(html.unescape(m.group(1)))
print(json.dumps(d.get('props',{})))
" 2>/dev/null
}

echo "=== Setup: XSRF + Login ==="
curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/login" -o /dev/null
XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")

CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" \
  -X POST "$BASE/login" \
  -H "X-XSRF-TOKEN: $XSRF" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"username":"admin","password":"password"}')
check "$CODE" "302" "Login"

curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/dashboard" -o /dev/null
XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")

echo ""
echo "=== VOD Index ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" "$BASE/admin/vod")
check "$CODE" "200" "GET /admin/vod"

BODY=$(curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/vod")
PROPS=$(echo "$BODY" | inertia_props)
echo "$PROPS" | python3 -c "import sys,json; d=json.load(sys.stdin); vods=d.get('vods',{}); items=vods.get('data',[]); print('  Items:', len(items)); any(print('  First poster_url:', items[0].get('poster_url','(none)')) for _ in [1]) if items else None" 2>/dev/null
echo "$PROPS" | grep -q "poster_url" && ok "Index has poster_url field" || fail "Index poster_url" "missing"

echo ""
echo "=== Create VOD (URL mode) ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" \
  -X POST "$BASE/admin/vod" \
  -H "X-XSRF-TOKEN: $XSRF" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"title":"Test Movie URL","type":"movie","year":2024,"stream_url":"http://example.com/test.mp4","poster_url":"https://image.tmdb.org/t/p/original/test.jpg","is_active":true,"is_featured":false,"category_ids":[],"bouquet_ids":[],"genre":["Action"],"cast":[]}')
check "$CODE" "302" "POST /admin/vod (URL mode)"

curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/dashboard" -o /dev/null
XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")

VOD_ID=$(docker exec iptv-middleware-app php -r "
require '/var/www/vendor/autoload.php';
\$app = require '/var/www/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$v = App\Models\VODContent::where('title','Test Movie URL')->latest()->first();
echo \$v ? \$v->id : '';
")
[ -n "$VOD_ID" ] && ok "URL VOD created id=$VOD_ID" || fail "URL VOD id" "empty"

echo ""
echo "=== Edit page — URL VOD (check props via HTML) ==="
if [ -n "$VOD_ID" ]; then
  BODY=$(curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/vod/$VOD_ID")
  PROPS=$(echo "$BODY" | inertia_props)
  echo "$PROPS" | python3 -c "
import sys,json
d=json.load(sys.stdin)
vod=d.get('vod',{})
print('  vodMedia present:', 'vodMedia' in vod, '| count:', len(vod.get('vodMedia',[])))
print('  bouquets present:', 'bouquets' in vod)
print('  stream_url:', vod.get('stream_url','MISSING')[:60])
print('  poster_url:', vod.get('poster_url','MISSING')[:60])
" 2>/dev/null
  echo "$PROPS" | python3 -c "import sys,json; d=json.load(sys.stdin); exit(0 if 'vodMedia' in d.get('vod',{}) else 1)" 2>/dev/null \
    && ok "Edit page vodMedia key present" || fail "Edit vodMedia key" "missing in props"
  echo "$PROPS" | python3 -c "import sys,json; d=json.load(sys.stdin); exit(0 if 'bouquets' in d.get('vod',{}) else 1)" 2>/dev/null \
    && ok "Edit page bouquets key present" || fail "Edit bouquets key" "missing in props"
  echo "$PROPS" | python3 -c "import sys,json; d=json.load(sys.stdin); exit(0 if d.get('vod',{}).get('poster_url') else 1)" 2>/dev/null \
    && ok "Edit page poster_url has value" || fail "Edit poster_url value" "empty"
  echo "$PROPS" | python3 -c "import sys,json; d=json.load(sys.stdin); exit(0 if d.get('vod',{}).get('stream_url') else 1)" 2>/dev/null \
    && ok "Edit page stream_url has value" || fail "Edit stream_url value" "empty"
fi

echo ""
echo "=== Create VOD (file upload mode) ==="
# Create a minimal valid MP4 (ftyp box) so Laravel's mimes validation passes
python3 -c "
import struct
# ftyp box: size=20, type='ftyp', major='mp42', version=0, compatible=['mp42','isom']
ftyp = struct.pack('>I4s4sI4s4s', 20, b'ftyp', b'mp42', 0, b'mp42', b'isom')
# mdat box: size=8, type='mdat' (empty)
mdat = struct.pack('>I4s', 8, b'mdat')
open('/tmp/test_vod_movie.mp4','wb').write(ftyp+mdat)
"
CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" \
  -X POST "$BASE/admin/vod/upload" \
  -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" \
  -F "title=Test Movie Upload" \
  -F "type=movie" \
  -F "year=2024" \
  -F "poster_url=https://image.tmdb.org/t/p/original/poster.jpg" \
  -F "is_active=1" \
  -F "is_featured=0" \
  -F "file=@/tmp/test_vod_movie.mp4;type=video/mp4")
check "$CODE" "302" "POST /admin/vod/upload (file)"

curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/dashboard" -o /dev/null
XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")

UPLOAD_ID=$(docker exec iptv-middleware-app php -r "
require '/var/www/vendor/autoload.php';
\$app = require '/var/www/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$v = App\Models\VODContent::where('title','Test Movie Upload')->latest()->first();
echo \$v ? \$v->id : '';
")
[ -n "$UPLOAD_ID" ] && ok "Upload VOD created id=$UPLOAD_ID" || fail "Upload VOD id" "empty"

echo ""
echo "=== Edit page — uploaded VOD ==="
if [ -n "$UPLOAD_ID" ]; then
  STREAM=$(docker exec iptv-middleware-app php -r "
require '/var/www/vendor/autoload.php';
\$app = require '/var/www/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$v = App\Models\VODContent::with('vodMedia')->find($UPLOAD_ID);
\$m = \$v ? \$v->vodMedia->first() : null;
echo \$m ? \$m->stream_url : 'NONE';
")
  echo "  stream_url in DB: $STREAM"
  echo "$STREAM" | grep -q "^/storage/" && ok "Uploaded file stored at /storage/" || fail "stream_url in vod_media" "got: $STREAM"

  BODY=$(curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/vod/$UPLOAD_ID")
  PROPS=$(echo "$BODY" | inertia_props)
  echo "$PROPS" | python3 -c "
import sys,json
d=json.load(sys.stdin)
vod=d.get('vod',{})
media=vod.get('vodMedia',[])
print('  vodMedia count:', len(media))
if media: print('  first stream_url:', media[0].get('stream_url','')[:60])
print('  poster_url:', vod.get('poster_url','MISSING')[:60])
" 2>/dev/null
  echo "$PROPS" | python3 -c "import sys,json; d=json.load(sys.stdin); m=d.get('vod',{}).get('vodMedia',[]); exit(0 if m and '/storage/' in (m[0].get('stream_url','')) else 1)" 2>/dev/null \
    && ok "Edit page vodMedia shows /storage/ path" || fail "Edit page /storage/ path" "not in vodMedia"
  echo "$PROPS" | python3 -c "import sys,json; d=json.load(sys.stdin); exit(0 if d.get('vod',{}).get('poster_url') else 1)" 2>/dev/null \
    && ok "Edit page poster_url present for uploaded VOD" || fail "Edit poster_url upload" "missing"
fi

echo ""
echo "=== Update VOD (PUT, no file) ==="
if [ -n "$VOD_ID" ]; then
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" \
    -X PUT "$BASE/admin/vod/$VOD_ID" \
    -H "X-XSRF-TOKEN: $XSRF" -H "Content-Type: application/json" -H "Accept: application/json" \
    -d "{\"title\":\"Test Movie URL Updated\",\"type\":\"movie\",\"year\":2024,\"stream_url\":\"http://example.com/updated.mp4\",\"is_active\":true,\"is_featured\":false,\"category_ids\":[],\"bouquet_ids\":[],\"genre\":[],\"cast\":[],\"season_count\":0,\"episode_count\":0}")
  check "$CODE" "302" "PUT /admin/vod/$VOD_ID (no file)"
  curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/dashboard" -o /dev/null
  XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
  XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")
fi

echo ""
echo "=== Update VOD with file (POST + _method=PUT) ==="
if [ -n "$UPLOAD_ID" ]; then
  # Recreate minimal MP4 in case it was cleaned up
  python3 -c "
import struct
ftyp = struct.pack('>I4s4sI4s4s', 20, b'ftyp', b'mp42', 0, b'mp42', b'isom')
mdat = struct.pack('>I4s', 8, b'mdat')
open('/tmp/test_vod_movie.mp4','wb').write(ftyp+mdat)
"
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" \
    -X POST "$BASE/admin/vod/$UPLOAD_ID" \
    -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" \
    -F "_method=PUT" \
    -F "title=Test Movie Upload Updated" \
    -F "type=movie" -F "year=2024" \
    -F "is_active=1" -F "is_featured=0" \
    -F "season_count=0" -F "episode_count=0" \
    -F "file=@/tmp/test_vod_movie.mp4;type=video/mp4")
  check "$CODE" "302" "POST+_method=PUT /admin/vod/$UPLOAD_ID (with file)"
  curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/dashboard" -o /dev/null
  XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
  XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")

  NEW_STREAM=$(docker exec iptv-middleware-app php -r "
require '/var/www/vendor/autoload.php';
\$app = require '/var/www/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$v = App\Models\VODContent::with('vodMedia')->find($UPLOAD_ID);
\$m = \$v ? \$v->vodMedia->where('season_number',0)->first() : null;
echo \$m ? \$m->stream_url : 'NONE';
")
  echo "  Updated stream_url: $NEW_STREAM"
  echo "$NEW_STREAM" | grep -q "^/storage/" && ok "Updated file stored at /storage/" || fail "Updated stream_url" "got: $NEW_STREAM"
fi

echo ""
echo "=== Delete VODs ==="
for DEL_ID in "$VOD_ID" "$UPLOAD_ID"; do
  [ -z "$DEL_ID" ] && continue
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" \
    -X DELETE "$BASE/admin/vod/$DEL_ID" \
    -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json")
  check "$CODE" "302" "DELETE /admin/vod/$DEL_ID"
  curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/admin/dashboard" -o /dev/null
  XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
  XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")
done

echo ""
echo "=== Verify deleted ==="
for CHECK_ID in "$VOD_ID" "$UPLOAD_ID"; do
  [ -z "$CHECK_ID" ] && continue
  RESULT=$(docker exec iptv-middleware-app php -r "
require '/var/www/vendor/autoload.php';
\$app = require '/var/www/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$v = App\Models\VODContent::withTrashed()->find($CHECK_ID);
echo \$v ? 'soft-deleted' : 'hard-deleted';
")
  [ "$RESULT" = "soft-deleted" ] || [ "$RESULT" = "hard-deleted" ] \
    && ok "VOD $CHECK_ID removed ($RESULT)" || fail "VOD $CHECK_ID" "still active"
done

rm -f "$COOKIES" /tmp/test_vod_movie.mp4
echo ""
echo "Results: $PASS passed, $FAIL failed"
[ $FAIL -eq 0 ] && exit 0 || exit 1
