#!/bin/sh
set -eu
umask 077
chmod 0700 /tmp
exec php "$@"
