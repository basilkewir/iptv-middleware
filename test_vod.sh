#!/bin/bash
set -e
BASE="http://localhost:25460"
COOKIES="/tmp/vod_test_cookies.txt"
rm -f "$COOKIES"

echo "=== 1. Fetch login page (get XSRF) ==="
curl -s -c "$COOKIES" -b "$COOKIES" -X GET "$BASE/login" -D - > /dev/null
XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")
echo "XSRF token: $([ -n "$XSRF" ] && echo YES || echo NO)"

echo ""
echo "=== 2. Login ==="
LOGIN_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -c "$COOKIES" -b "$COOKIES" \
  -X POST "$BASE/login" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"username":"admin","password":"password"}')
echo "Login HTTP: $LOGIN_CODE (302=OK)"

echo ""
echo "=== 3. Refresh XSRF after login ==="
curl -s -c "$COOKIES" -b "$COOKIES" -X GET "$BASE/admin/dashboard" > /dev/null
XSRF_RAW=$(grep 'XSRF-TOKEN' "$COOKIES" | awk '{print $7}')
XSRF=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$XSRF_RAW'))")

echo ""
echo "=== 4. GET /admin/vod (index) ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" "$BASE/admin/vod")
echo "VOD index: $CODE (200=OK)"

echo ""
echo "=== 5. GET /admin/vod/create ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" "$BASE/admin/vod/create")
echo "VOD create page: $CODE (200=OK)"

echo ""
echo "=== 6. POST /admin/vod (store) ==="
STORE_RESP=$(curl -s -w "\nHTTP:%{http_code}" \
  -c "$COOKIES" -b "$COOKIES" \
  -X POST "$BASE/admin/vod" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"title":"Test VOD HTTP","type":"movie","is_active":true,"bouquet_ids":[],"category_ids":[]}')
STORE_CODE=$(echo "$STORE_RESP" | grep 'HTTP:' | cut -d: -f2)
echo "VOD store: $STORE_CODE (302=OK)"

echo ""
echo "=== 7. Find the created VOD ==="
VOD_ID=$(docker exec iptv-middleware-app php -r "
require '/var/www/vendor/autoload.php';
\$app = require '/var/www/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$v = App\Models\VODContent::where('title','Test VOD HTTP')->first();
echo \$v ? \$v->id : 'NOT_FOUND';
")
echo "VOD ID: $VOD_ID"

echo ""
echo "=== 8. GET /admin/vod/{id}/edit ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" "$BASE/admin/vod/$VOD_ID/edit")
echo "VOD edit page: $CODE (200=OK)"

echo ""
echo "=== 9. PUT /admin/vod/{id} (update) ==="
UPDATE_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -c "$COOKIES" -b "$COOKIES" \
  -X PUT "$BASE/admin/vod/$VOD_ID" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"title\":\"Test VOD HTTP Updated\",\"type\":\"movie\",\"is_active\":true,\"bouquet_ids\":[],\"category_ids\":[]}")
echo "VOD update: $UPDATE_CODE (302=OK)"

echo ""
echo "=== 10. POST /admin/vod/{id}/episodes (store episode) ==="
EP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -c "$COOKIES" -b "$COOKIES" \
  -X POST "$BASE/admin/vod/$VOD_ID/episodes" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"season_number":1,"episode_number":1,"episode_title":"Pilot","stream_url":"http://test/s01e01.m3u8"}')
echo "Episode store: $EP_CODE (201=OK)"

echo ""
echo "=== 11. GET /admin/vod/{id}/episodes ==="
EP_LIST=$(curl -s -w "\nHTTP:%{http_code}" \
  -c "$COOKIES" -b "$COOKIES" \
  -H "Accept: application/json" \
  "$BASE/admin/vod/$VOD_ID/episodes")
EP_CODE=$(echo "$EP_LIST" | grep 'HTTP:' | cut -d: -f2)
echo "Episode list: $EP_CODE (200=OK)"
echo "$EP_LIST" | grep -v 'HTTP:' | python3 -c "import sys,json; d=json.load(sys.stdin); print('  Episodes in season 1:', len(d['data'][0]['episodes']) if d.get('data') else 0)" 2>/dev/null || true

echo ""
echo "=== 12. Nginx upload limit (413 test — 1MB payload) ==="
dd if=/dev/urandom bs=1024 count=1024 2>/dev/null | base64 > /tmp/fake_payload.txt
UPLOAD_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -c "$COOKIES" -b "$COOKIES" \
  -X POST "$BASE/admin/vod/$VOD_ID/episodes/upload" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Accept: application/json" \
  -F "season_number=1" \
  -F "episode_number=2" \
  -F "file=@/tmp/fake_payload.txt;type=text/plain")
echo "1MB upload attempt: $UPLOAD_CODE (422=validation fail, NOT 413)"

echo ""
echo "=== 13. Cleanup ==="
docker exec iptv-middleware-app php -r "
require '/var/www/vendor/autoload.php';
\$app = require '/var/www/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$v = App\Models\VODContent::where('title','Test VOD HTTP Updated')->first();
if (\$v) { \$v->vodMedia()->delete(); \$v->bouquets()->detach(); \$v->categories()->detach(); \$v->forceDelete(); echo 'Cleaned up VOD id=' . \$v->id . PHP_EOL; }
else { echo 'VOD not found for cleanup' . PHP_EOL; }
"

echo ""
echo "=== All tests complete ==="
