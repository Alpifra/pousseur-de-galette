#!/bin/sh
set -e

# CLI commands and the worker run as root while PHP-FPM runs as www-data: default ACLs
# keep everything created under var/ writable by both
mkdir -p var/cache var/log
setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var

exec docker-php-entrypoint "$@"
