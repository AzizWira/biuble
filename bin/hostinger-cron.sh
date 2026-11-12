#!/bin/sh
set -eu

cd "$(dirname "$0")/.."
flock -n storage/framework/biuble-expire.lock /usr/bin/php artisan biuble:expire --no-interaction

case "$(date -u +%M)" in
    *0|*5)
        flock -n storage/framework/biuble-reconcile.lock /usr/bin/php artisan biuble:reconcile --no-interaction
        ;;
esac
