<?php

require __DIR__ . "/../../autoload.php";

$temp_dir = CORE_DIR .'/scripts/temp';
mkdir($temp_dir, 0777);
$zip_path = __DIR__ . '/arte-app.zip';
@unlink($zip_path);

mkdir($temp_dir . '/app', 0777);
copyDirectory(APP_DIR, $temp_dir . '/app');

mkdir($temp_dir . '/logs', 0777);
copyDirectory(LOG_DIR, $temp_dir . '/logs');  
mkdir($temp_dir . '/media', 0777);
copyDirectory(MEDIA_DIR, $temp_dir . '/media');

mkdir($temp_dir . '/plugins', 0777);
copyDirectory(PLUGINS_DIR, $temp_dir . '/plugins');

mkdir($temp_dir . '/vendor', 0777);
copyDirectory(ROOT_DIR . '/vendor', $temp_dir . '/vendor');

copy(ROOT_DIR . '/.env', $temp_dir . '/.env');
copy(ROOT_DIR . '/.gitignore', $temp_dir . '/.gitignore');
copy(ROOT_DIR . '/composer.json', $temp_dir . '/composer.json');
copy(ROOT_DIR . '/composer.lock', $temp_dir . '/composer.lock');
copy(ROOT_DIR . '/config.php', $temp_dir . '/config.php');
copy(ROOT_DIR . '/routes.json', $temp_dir . '/routes.json');

zipDirectory($temp_dir, $zip_path);
deleteDirectory($temp_dir);
echo "zip created at $zip_path\n";