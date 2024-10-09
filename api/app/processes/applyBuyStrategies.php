<?php

require_once __DIR__ . "../../../autoload.php";

const MIN_WAIT = 5; // secs
const INIT_TIME = 0; // secs
$initiator = null;
$timing = new Timing('buy');
$cache = ArteCache::getInst();
$config = Config::getInst();

while (true) {
  if (!isset($initiator)) {
    countdown("Fetching market status", INIT_TIME * 60);
    $initiator = new Initiator('buy');
  }

  $initiator->refresh();

  if ($timing->marketReady()) {
    foreach ($config['buy'] as $n => $run) {
      $right_before_run = $timing->getMarketTime()->addMinutes($run['trade_after'] - 1);

      if ((new Ndate)->minutesUntil($right_before_run) < -5) {
        echo "Ignoring Run " . $n + 1 . "\n";
        continue;
      }

      $timing->waitTill($right_before_run);
      $processes = $initiator->prepare($run);
      $timing->waitTillRun($run);

      $start = microtime(true);
      echo (new Ndate)->format(Ndate::DATE_TIME) . "\n";

      if (!isset($run['dir']))
        $run['dir'] = null;
      $stocks = $cache->get('stocks', [$run['sid'], 'buy', $run['number_of_trades'], null, null, $run['dir']]);

      $cache_path = $cache->export(count($stocks));

      // Preparing logs 
      foreach ($stocks as $i => $stock) {
        $process_args = [
          'symbol' => $stock['symbol'],
          'cache' => $cache_path,
          'budget' => $run['buying_power_percent'] * $cache->get('buying_power') / count($stocks),
          'stop_loss_percent' => $run['stop_loss_percent'],
          'sequences' => $run['sequences'],
          'sid' => $run['sid'],
          'dir' => $run['dir'] ?? null
        ];

        if ($config['globals']['no-revert'])
          $process_args['no-revert'] = true;

        $processes[$i]->passArgs($process_args, true);
        echo "Trading {$stock['symbol']}\n";
        $processes[$i]->run(Process::BACKGROUND);
        echo "\n";
      }

      $time_taken = microtime(true) - $start;
      echo "$time_taken secs in initializing runs\n";

      echo "Cache Loading Logs:\n";
      prettyPrint($cache->logs);
      $cache->logs = [];
    }

    $timing->waitTill($config['globals']['until']);
  } else {
    $timing->waitTill($timing->getMarketTime()->addMinutes(-2));
  }
}
