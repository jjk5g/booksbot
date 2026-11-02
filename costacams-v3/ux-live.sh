#!/bin/sh
set -eu
BASE=/home/u751177156/domains/costacams.com/public_html
SRC="$BASE/ccdeploy-v3/costacams-v3"
mkdir -p "$BASE/ss"
for f in home.php map-health.php map-home.php; do php -l "$SRC/$f" >/dev/null; done
cp -p "$BASE/.htaccess" "$BASE/ss/htaccess-before-shark-per-beach-camera-health.txt"
[ ! -f "$BASE/map-health.php" ] || cp -p "$BASE/map-health.php" "$BASE/ss/map-health-before-shark-per-beach.php"
[ ! -f "$BASE/map-home.php" ] || cp -p "$BASE/map-home.php" "$BASE/ss/map-home-before-shark-per-beach.php"
[ ! -f "$BASE/home.php" ] || cp -p "$BASE/home.php" "$BASE/ss/home-before-kingscliff-filter.php"
cp -p "$SRC/home.php" "$BASE/home.php"
cp -p "$SRC/map-health.php" "$BASE/map-health.php"
cp -p "$SRC/map-home.php" "$BASE/map-home.php"
cp -p "$SRC/.htaccess" "$BASE/.htaccess"
rm -f /tmp/costacams-map-home-v2.html /tmp/costacams-map-home-v3.html
curl -fsS https://costacams.com/map >/dev/null
curl -fsS https://costacams.com/ >/dev/null
echo COSTACAMS_UX_LIVE_OK
