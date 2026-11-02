#!/bin/sh
set -eu
BASE=/home/u751177156/domains/costacams.com/public_html
SRC="$BASE/ccdeploy-v3/costacams-v3"
mkdir -p "$BASE/ss"
php -l "$SRC/map-health.php" >/dev/null
php -l "$SRC/map-home.php" >/dev/null
cp -p "$BASE/.htaccess" "$BASE/ss/htaccess-before-camera-health.txt"
[ ! -f "$BASE/map-home.php" ] || cp -p "$BASE/map-home.php" "$BASE/ss/map-home-before-camera-health.php"
cp -p "$SRC/map-health.php" "$BASE/map-health.php"
cp -p "$SRC/map-home.php" "$BASE/map-home.php"
cp -p "$SRC/.htaccess" "$BASE/.htaccess"
rm -f /tmp/costacams-map-home-v2.html /tmp/costacams-map-home-v3.html
curl -fsS https://costacams.com/map >/dev/null
echo COSTACAMS_CAMERA_HEALTH_LIVE_OK
