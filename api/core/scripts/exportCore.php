<?php

require __DIR__ . "/../../autoload.php";

$temp_dir = CORE_DIR .'/scripts/temp';
mkdir($temp_dir, 0777);
$zip_path = __DIR__ . '/arte-core.zip';
@unlink($zip_path);

copyDirectory(CORE_DIR, $temp_dir);
mkdir($temp_dir . '/root');
copy(ROOT_DIR . '/.htaccess', $temp_dir . '/root/.htaccess');
copy(ROOT_DIR . '/index.php', $temp_dir . '/root/index.php');
copy(ROOT_DIR . '/autoload.php', $temp_dir . '/root/autoload.php');
copy(ROOT_DIR . '/.env.example', $temp_dir . '/root/.env.example');

zipDirectory($temp_dir, $zip_path);
deleteDirectory($temp_dir);
echo "zip created at $zip_path\n";