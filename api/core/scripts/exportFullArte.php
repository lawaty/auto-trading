<?php

require __DIR__ . "/../../autoload.php";

$temp_dir = CORE_DIR .'/scripts/temp';
mkdir($temp_dir, 0777);
$zip_path = __DIR__ . '/full-arte.zip';
@unlink($zip_path);

copyDirectory(ROOT_DIR, $temp_dir);

unlink($temp_dir . '/.env');
unlink($temp_dir . '/config.php');
deleteDirectory($temp_dir . '/logs');
mkdir($temp_dir . '/logs', 0777);
deleteDirectory($temp_dir . '/media');
mkdir($temp_dir . '/media', 0777);
deleteDirectory($temp_dir . '/plugins');
mkdir($temp_dir . '/plugins', 0777);

zipDirectory($temp_dir, $zip_path);
deleteDirectory($temp_dir);
echo "zip created at $zip_path\n";