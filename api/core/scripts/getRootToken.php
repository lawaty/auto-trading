<?php

require __DIR__ . "/../../autoload.php";

$root_token = Authenticator::encode([
  'time' => (new Ndate)->format(Ndate::DATE_TIME),
  'root' => true
]);

echo $root_token . "\n";