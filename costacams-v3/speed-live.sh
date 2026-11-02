#!/bin/sh
set -eu
BASE=/home/u751177156/domains/costacams.com/public_html
SRC="$BASE/ccdeploy-v3/costacams-v3"
mkdir -p "$BASE/ss"
cp -p "$BASE/site.php" "$BASE/ss/site-before-speed.php"
cp -p "$BASE/map-home.php" "$BASE/ss/map-home-before-speed.php"
cp -p "$BASE/.htaccess" "$BASE/ss/htaccess-before-speed.txt"
cp -p "$SRC/site.php" "$BASE/site.php"
cp -p "$SRC/map-home.php" "$BASE/map-home.php"
cp -p "$SRC/.htaccess" "$BASE/.htaccess"
rm -f /tmp/costacams-home-v6.html /tmp/costacams-map-home-v2.html
echo COSTACAMS_SPEED_LIVE_OK
