#!/bin/sh
set -eu
B=/home/u751177156/domains/costacams.com/public_html
S="$B/ccdeploy-v3/costacams-v3"
mkdir -p "$B/ss"
cp "$B/app.php" "$B/ss/app-before-kingscliff-final.php"
cp "$B/map-health.php" "$B/ss/map-health-before-shark-zones.php"
php -l "$S/app.php" >/dev/null
php -l "$S/map-health.php" >/dev/null
cp "$S/app.php" "$B/app.php"
cp "$S/map-health.php" "$B/map-health.php"
rm -f /tmp/costacams-app-home-v2.html /tmp/costacams-home-v6.html /tmp/costacams-map-home-v2.html /tmp/costacams-map-home-v3.html
echo COSTACAMS_MAP_HOME_FINALIZED