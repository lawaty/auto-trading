<?php

require_once __DIR__ . "../../../autoload.php";

const MIN_WAIT = 5; // secs
const INIT_TIME = 0; // secs
$initiator = null;
$timing = new Timing('short');
$cache = ArteCache::getInst();

$config = Config::getInst();

while (true) {
  if (!isset($initiator)) {
    countdown("Fetching market status", INIT_TIME * 60);
    $initiator = new Initiator('short');
  }

  $initiator->refresh();

  if ($timing->marketReady()) {
    $cache_path = '';
    foreach ($config['short'] as $n => $run) {
      @unlink($cache_path);
      $right_before_run = $timing->getMarketTime()->addMinutes($run['trade_after'] - 1);

      if ((new Ndate)->minutesUntil($right_before_run) < -5) {
        echo "Ignoring Run " . $n + 1 . "\n";
        continue;
      }
      
      $timing->waitTill($right_before_run);
      $processes = $initiator->prepare($run);
      $cache_path = $cache->export();
      $timing->waitTillRun($run);

      $start = microtime(true);
      $stocks = $cache->get('stocks', [$run['sid'], 'short', $run['number_of_trades']]);

      // Preparing logs
      foreach ($stocks as $i => $stock) {
        $process_args = [
          'symbol' => $stock['symbol'],
          'cache' => $cache_path,
          'budget' => $run['buying_power_percent'] * $cache->get('buying_power') / count($stocks),
          'stop_loss_percent' => $run['stop_loss_percent'],
          'sequences' => $run['sequences']
        ];

        $processes[$i]->passArgs($process_args, true);
        $processes[$i]->run(Process::BACKGROUND);
      }

      $time_taken = microtime(true) - $start;
      echo "$time_taken secs in initializing runs\n";
    }

    echo "Cache Loading Logs:\n";
    prettyPrint($cache->logs);
    $timing->waitTill($config['globals']['until']);
  } else {
    $timing->waitTill($timing->getMarketTime());
  }
}
