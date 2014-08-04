<?php

error_reporting(E_ERROR);

if(!file_exists('config.php'))
    die('config.php file does not exists. Use config.sample.php to create your configuration.');

$dir = __DIR__;

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

$copy = new \Barracuda\Copy\API(CONSUMER_KEY, CONSUMER_SECRET, TOKEN, SECRET);
$fh = fopen($localFilePath, 'rb');
$parts = [];
while ($data = fread($fh, 1024 * 1024)) {
    $parts[] = $copy->sendData($data);
}
fclose($fh);
$copy->createFile($copyConfig->backup_dir . $filename, $parts);

$backups = $copy->listPath($copyConfig->backup_dir);
foreach($backups as $backup){
    if($oldFilesFrom > $backup->created_time){
        $copy->removeFile($backup->path);
    }
}

if(LOCAL_DIR){
    rename($localFilePath, LOCAL_DIR . '/' . $fileName . '.tgz');
    $dir = opendir(LOCAL_DIR);
    $oldFilesFrom = time() - BACKUP_EXPIRATION;
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



