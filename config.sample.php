<?php
define('CONSUMER_KEY', '');
define('CONSUMER_SECRET', '');
define('TOKEN', '');
define('SECRET', '');

define('MYSQLDUMP', 'mysqldump');
define('DB_USERNAME', '');
define('DB_DATABASE', ''); // multiple database names split with space
define('MY_CNF_PATH', '~/.my.cnf');

// set to false if don't want to store local files
define('LOCAL_DIR', __DIR__ . '/backups');
define('COPY_DIR', '/backup');
define('FILENAME_PREFIX', '');
define('BACKUP_EXPIRATION', 86400 * 3); // 3 days
