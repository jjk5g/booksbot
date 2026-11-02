#!/bin/sh
set -eu
BASE=/home/u751177156/domains/costacams.com/public_html
SRC="$BASE/ccdeploy-v3/costacams-v3"
mkdir -p "$BASE/ss"
cp -p "$BASE/index.html" "$BASE/ss/index.html"
for f in .htaccess admin.php bootstrap.php contact.php index.php map.php map-home.php presence.php report.php robots.txt schema.sql server-config.php site.php sitemap.xml stream-health.php; do
  cp -p "$SRC/$f" "$BASE/$f"
done
rm -f "$BASE/index.html"
echo COSTACAMS_LIVE_OK
