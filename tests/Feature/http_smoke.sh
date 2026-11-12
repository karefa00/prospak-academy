#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
port="${1:-8019}"
php -S "127.0.0.1:$port" -t public public/index.php > /tmp/prospak-http-smoke-$$.log 2>&1 &
pid=$!
trap 'kill "$pid" 2>/dev/null || true; rm -f /tmp/prospak-http-smoke-$$.log' EXIT
sleep 1
login=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/login")
redirect=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/dashboard")
csrf=$(curl -s -o /dev/null -w '%{http_code}' -X POST -d 'email=x@y.z&password=bad' "http://127.0.0.1:$port/login")
missing=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/no-such-route")
[ "$login" = '200' ] && [ "$redirect" = '303' ] && [ "$csrf" = '419' ] && [ "$missing" = '404' ] || { echo "FAIL HTTP: login=$login auth-redirect=$redirect csrf=$csrf not-found=$missing"; exit 1; }
echo 'PASS: HTTP login=200 protected=303 CSRF=419 not-found=404'
