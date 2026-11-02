#!/bin/sh
set -eu
B=/home/u751177156/domains/costacams.com/public_html
S="$B/ccdeploy-v3/costacams-v3"
mkdir -p "$B/ss" "$B/cache"
for f in render-fast.php contact-fast.php page-warm.php map-health.php map-home.php; do php -l "$S/$f" >/dev/null; done
cp -p "$B/.htaccess" "$B/ss/htaccess-before-static-fast.txt"
for f in page-warm.php map-health.php map-home.php; do [ ! -f "$B/$f" ] || cp -p "$B/$f" "$B/ss/$f.before-static-fast"; done
cp -p "$S/render-fast.php" "$B/render-fast.php"
cp -p "$S/contact-fast.php" "$B/contact-fast.php"
cp -p "$S/page-warm.php" "$B/page-warm.php"
cp -p "$S/map-health.php" "$B/map-health.php"
cp -p "$S/map-home.php" "$B/map-home.php"
cp -p "$S/.htaccess" "$B/.htaccess"
rm -f /tmp/costacams-map-home-v3.html /tmp/costacams-map-home-v4.html
php "$B/page-warm.php"
echo COSTACAMS_STATIC_FAST_LIVE_OK
