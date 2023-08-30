#!/usr/bin/env bash

cd /var/www/html || exit 1

rm -rf var
php bin/console cache:warmup

/etc/init.d/supervisor start
supervisorctl reread
supervisorctl update
supervisorctl start messenger-consume:*

[ -z ${APP_CACHE_DIR+x} ] || chown -R 33:33 "$APP_CACHE_DIR"
[ -z ${APP_LOG_DIR+x} ] || chown -R 33:33 "$APP_LOG_DIR"
[ -z ${LOCAL_FILE_UPLOADER_PATH+x} ] || chown -R 33:33 "$LOCAL_FILE_UPLOADER_PATH"

exec apache2ctl -D FOREGROUND
