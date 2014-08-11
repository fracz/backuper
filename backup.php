<?php

error_reporting(E_ERROR);

$dir = __DIR__;

if (!file_exists($dir . '/config.php'))
    die('config.php file does not exists. Use config.sample.php to create your configuration.');

if (!file_exists($dir . '/vendor/autoload.php'))
    die('Download composer dependencies.');

//if (!file_exists('~/.my.cnf'))
//    die('Create ~/.my.cnf file with database access configuration.');

require $dir . '/config.php';
require_once $dir . '/vendor/autoload.php';

$cli = new \Commando\Command();

$cli->option()
    ->require()
    ->describedAs('Database name. Specify multiple databases by comma.')
    ->must(function($dbName) { return strlen($dbName) > 0; })
    ->default(DB_NAME);

$cli->option('u')
    ->require()
    ->aka('username')
    ->must(function($username) { return strlen($username) > 0; })
    ->describedAs('Username to connect to the database (should be the same as in ~/.my.cnf file.')
    ->default(DB_USERNAME);

$cli->option('p')
    ->aka('prefix')
    ->describedAs('Prefix of the backup filename.')
    ->default(FILENAME_PREFIX);

$cli->option('d')
    ->aka('dir')
    ->describedAs('Name of the Copy directory where the backup should be stored.')
    ->default(COPY_DIR);

$cli->flag('v')
    ->boolean()
    ->aka('verbose')
    ->describedAs('Turns on verbose mode.');

$dirPath = $dir;
$fileName = $cli['p'] . date('Y-m-d_H.i.s');
$localFilePath = "$dirPath/$fileName";

$cmd = sprintf("%s -u %s --databases %s > %s",
    MYSQLDUMP,
    escapeshellcmd($cli['u']),
    escapeshellcmd($cli[0]),
    escapeshellcmd($localFilePath . '.sql'));

if($cli['v'])
    echo 'Executing: ' . $cmd . PHP_EOL;

exec($cmd);

exec('cd ' . $dirPath . ' && tar czf ' . $fileName . '.tgz ' . $fileName . '.sql');

unlink($localFilePath . '.sql');

$localFilePath .= '.tgz';
$oldFilesFrom = time() - BACKUP_EXPIRATION;

try {
    $copy = new \Barracuda\Copy\API(CONSUMER_KEY, CONSUMER_SECRET, TOKEN, SECRET);
    $fh = fopen($localFilePath, 'rb');
    $parts = [];
    while ($data = fread($fh, 1024 * 1024)) {
        $parts[] = $copy->sendData($data);
    }
    fclose($fh);
    $copy->createFile($cli['d'] . '/' . $fileName . '.tgz', $parts);

    $backups = $copy->listPath($cli['d']);
    foreach ($backups as $backup) {
        if ($oldFilesFrom > $backup->created_time && end(explode('.', $backup->path)) == 'tgz') {
            $copy->removeFile($backup->path);
        }
    }
} catch (Exception $e) {
    echo 'Unable to upload file to Copy' . PHP_EOL;
    echo $e;
}

if (LOCAL_DIR) {
    rename($localFilePath, LOCAL_DIR . '/' . $fileName . '.tgz');
    $dir = opendir(LOCAL_DIR);
    while ($file = readdir($dir)) {
        if (end(explode('.', $file)) == 'tgz') {
            $time = filemtime($dirPath . '/' . $file);
            if ($time < $oldFilesFrom) {
                unlink($dirPath . '/' . $file);
            }
        }
    }
} else {
    unlink($localFilePath);
}
