<?php

error_reporting(E_ERROR);

$dir = __DIR__;

if (!file_exists($dir . '/config.php'))
    die('config.php file does not exists. Use config.sample.php to create your configuration.');

if (!file_exists($dir . '/vendor/autoload.php'))
    die('Download composer dependencies.');

require $dir . '/config.php';
require_once $dir . '/vendor/autoload.php';


$fileName = date('Y.m.d_H.i');
$dirPath = $dir;
$localFilePath = "$dirPath/$fileName";

$cmd = sprintf("%s -u %s --databases %s > %s",
    MYSQLDUMP,
    escapeshellcmd(DB_USERNAME),
    escapeshellcmd(DB_DATABASE),
    escapeshellcmd($localFilePath . '.sql'));

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
    $copy->createFile(COPY_DIR . '/' . $fileName . '.tgz', $parts);

    $backups = $copy->listPath(COPY_DIR);
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
