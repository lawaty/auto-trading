<?php

require_once __DIR__ . "../../../autoload.php";

const MIN_WAIT = 5; // secs
const INIT_TIME = 0; // secs
$initiator = null;

while (true) {
  if (!isset($initiator)) {
    countdown("Fetching market status", INIT_TIME * 60);
    $initiator = new Initiator('buy');
  }

  try {
    $initiator->refresh();
    
    if ($initiator->marketOpen()) {
      try {
        $initiator->prepare();
        $initiator->instantiateRuns();
      } catch (Exception |  Error $e) {
        echo "Encountered some weird behavior!\n {trace($e)}\n\n Quitting Market... \n\n";
        var_dump($e);
      }

      $initiator->sleep();
    }
    else {
      $initiator->sleep();
    }
  } catch (Exception | Error $e) {
    file_put_contents(__DIR__ . '/logs/Buy-' . (new Ndate)->format() . '.err', trace($e) . "\n", FILE_APPEND);
  }
}