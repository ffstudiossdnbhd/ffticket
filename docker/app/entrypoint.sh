#!/bin/sh
set -eu

if [ "${1:-}" = "apache2-foreground" ]; then
    php /opt/ffticket/bootstrap-admin.php
fi

exec docker-php-entrypoint "$@"
