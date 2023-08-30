#!/usr/bin/env bash

cd /var/www/html || exit 1

rm -rf var
php bin/console cache:warmup

/etc/init.d/supervisor start
supervisorctl reread
supervisorctl update
supervisorctl start messenger-consume:*

exec apache2ctl -D FOREGROUND
