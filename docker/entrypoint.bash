#!/usr/bin/env bash

cd /var/www/html || exit 1

rm -rf var
php bin/console cache:warmup

supervisorctl reread
supervisorctl update
supervisorctl start messenger-consume:*

exec apache2ctl -D FOREGROUND
