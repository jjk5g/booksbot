#!/bin/sh
set -eu
BASE=/home/u751177156/domains/costacams.com/public_html
SRC="$BASE/ccdeploy-v3/costacams-v3"
mkdir -p "$BASE/ss"
for f in app.php channel.php map.php content-ingest.php page-warm.php; do php -l "$SRC/$f" >/dev/null; done
cp -p "$BASE/.htaccess" "$BASE/ss/htaccess-before-channel-shark-hotfix.txt"
[ ! -f "$BASE/.user.ini" ] || cp -p "$BASE/.user.ini" "$BASE/ss/user-ini-before-channel-shark-hotfix.txt"
for f in app.php channel.php map.php content-ingest.php; do [ ! -f "$BASE/$f" ] || cp -p "$BASE/$f" "$BASE/ss/${f%.php}-before-channel-shark-hotfix.php"; done
cp -p "$SRC/app.php" "$BASE/app.php"
cp -p "$SRC/channel.php" "$BASE/channel.php"
cp -p "$SRC/map.php" "$BASE/map.php"
cp -p "$SRC/content-ingest.php" "$BASE/content-ingest.php"
cp -p "$SRC/page-warm.php" "$BASE/page-warm.php"
cp -p "$SRC/.htaccess" "$BASE/.htaccess"
cp -p "$SRC/.user.ini" "$BASE/.user.ini"
rm -f /tmp/costacams-app-channel-*-v2.html /tmp/costacams-map-home-v2.html
php "$BASE/content-ingest.php"
php "$BASE/page-warm.php"
curl -fsS https://costacams.com/main-beach >/dev/null
curl -fsS https://costacams.com/map >/dev/null
echo COSTACAMS_CHANNEL_SHARK_HOTFIX_OK
