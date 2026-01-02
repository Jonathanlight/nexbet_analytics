usermod -u $USER_ID www-data
groupmod -g $GROUP_ID www-data
umask 0000
php-fpm --nodaemonize