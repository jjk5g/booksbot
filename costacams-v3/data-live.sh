#!/bin/sh
set -eu
BASE=/home/u751177156/domains/costacams.com/public_html
SRC="$BASE/ccdeploy-v3/costacams-v3"
mkdir -p "$BASE/ss"
for f in app.php channel.php data-refresh.php content-ingest.php shark-refresh.php; do
  php -l "$SRC/$f" >/dev/null
done
cp -p "$BASE/.htaccess" "$BASE/ss/htaccess-before-data-system.txt"
[ ! -f "$BASE/app.php" ] || cp -p "$BASE/app.php" "$BASE/ss/app-before-data-system.php"
[ ! -f "$BASE/channel.php" ] || cp -p "$BASE/channel.php" "$BASE/ss/channel-before-data-system.php"
cp -p "$SRC/app.php" "$BASE/app.php"
cp -p "$SRC/channel.php" "$BASE/channel.php"
cp -p "$SRC/data-refresh.php" "$BASE/data-refresh.php"
cp -p "$SRC/content-ingest.php" "$BASE/content-ingest.php"
cp -p "$SRC/shark-refresh.php" "$BASE/shark-refresh.php"
cp -p "$SRC/.htaccess" "$BASE/.htaccess"
rm -f /tmp/costacams-app-home-v1.html /tmp/costacams-home-v6.html
php "$BASE/data-refresh.php"
php "$BASE/content-ingest.php"
php "$BASE/shark-refresh.php"
echo COSTACAMS_DATA_SYSTEM_LIVE_OK
