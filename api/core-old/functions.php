<?php

if (!function_exists('str_contains')) {
  function str_contains(string $haystack, string $needle): bool
  {
    return '' === $needle || false !== strpos($haystack, $needle);
  }
}


function isJson($payload)
{
  if (!is_string($payload))
    return false;

  if (!empty($payload) && $payload[0] != '{' && $payload[0] != '[')
    return false;

  json_decode($payload);
  return json_last_error() === JSON_ERROR_NONE;
}

function trace($e, $seen = null): string
{
  $starter = $seen ? 'Caused by: ' : '';
  $result = array();
  if (!$seen) $seen = array();
  $trace = $e->getTrace();
  $prev = $e->getPrevious();
  $result[] = sprintf('%s%s: %s', $starter, get_class($e), $e->getMessage());
  $file = $e->getFile();
  $line = $e->getLine();
  while (true) {
    $current = "$file:$line";
    if (is_array($seen) && in_array($current, $seen)) {
      $result[] = sprintf(' ... %d more', count($trace) + 1);
      break;
    }
    $result[] = sprintf(
      ' at %s%s%s(%s%s%s)',
      count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',
      count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',
      count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',
      $line === null ? $file : basename($file),
      $line === null ? '' : ':',
      $line === null ? '' : $line
    );
    if (is_array($seen))
      $seen[] = "$file:$line";
    if (!count($trace))
      break;
    $file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
    $line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
    array_shift($trace);
  }
  $result = join("\n", $result);
  if ($prev)
    $result .= "\n" . trace($prev, $seen);

  return $result;
}

function isAssoc(array $array)
{
  return array_keys($array) !== range(0, count($array) - 1);
}

function deleteDirectory(string $dir)
{
  if (!is_dir($dir)) {
    return false;
  }

  $files = array_diff(scandir($dir), array('.', '..'));

  foreach ($files as $file) {
    $path = $dir . '/' . $file;

    if (is_dir($path)) {
      deleteDirectory($path);
    } else {
      unlink($path);
    }
  }

  return rmdir($dir);
}

function chmodRecursive($dir, $permission)
{
  if (!file_exists($dir)) {
    return;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );

  foreach ($iterator as $item) {
    chmod($item, $permission);
  }
}

function prettyPrint(array $arr)
{
  echo '<pre>';
  print_r($arr);
  echo '</pre>';
}

function copyDirectory($source, $destination)
{
  // Create the destination directory if it doesn't exist
  if (!is_dir($destination)) {
    mkdir($destination, 0777, true);
  }

  // Open the source directory
  $dir = opendir($source);

  // Loop through all files and subdirectories in the source directory
  while (false !== ($file = readdir($dir))) {
    if ($file == '.' || $file == '..' || $file == 'scripts') {
      continue; // Skip current and parent directory entries
    }

    $sourcePath = $source . '/' . $file;
    $destinationPath = $destination . '/' . $file;

    if (is_dir($sourcePath)) {
      // If the current item is a directory, recursively copy it
      copyDirectory($sourcePath, $destinationPath);
    } else {
      // If it's a file, copy it to the destination
      copy($sourcePath, $destinationPath);
    }
  }

  // Close the source directory
  closedir($dir);
}

function zipDirectory($source, $destination)
{
  // Initialize ZipArchive object
  $zip = new ZipArchive();

  // Open or create the zip file
  if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    return false;
  }

  // Create recursive directory iterator
  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source),
    RecursiveIteratorIterator::LEAVES_ONLY
  );

  // Loop through each file and add it to the zip archive
  foreach ($files as $name => $file) {
    // Skip directories (they will be added automatically)
    if (!$file->isDir()) {
      // Get the relative path of the file
      $filePath = $file->getRealPath();
      $relativePath = substr($filePath, strlen($source) + 1);

      // Add the file to the zip archive
      $zip->addFile($filePath, $relativePath);
    }
  }

  // Close the zip archive
  $zip->close();

  return true;
}
