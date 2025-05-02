<?php

require_once __DIR__ . "../../../autoload.php";

const INIT_TIME = 0; // secs
$initiator = null;
$timing = new Timing('short');
$cache = ArteCache::getInst();
$config = Config::getInst();
$trade_station = new TradeStation('short');

while (true) {
  if (!isset($initiator)) {
    countdown("Fetching market status", INIT_TIME * 60);
    $initiator = new Initiator('short');
  }

  $initiator->refresh();

  if ($timing->marketReady()) {
    $leave_percent = $config['globals']['leave_percent'];
    $starting_equity = $trade_station->getEquity();
    foreach ($config['short'] as $n => $run) {
      $right_before_run = $timing->getMarketTime()->addMinutes($run['trade_after'] - 1);

      if ((new Ndate)->minutesUntil($right_before_run) < -5) {
        echo "Ignoring Run " . $n + 1 . "\n";
        continue;
      }

      $timing->waitTill($right_before_run); // POLA prefers putting the wait logic at the end of each loop for better readability
      $current_equity = $trade_station->getEquity();
      if ($leave_percent < 0 && ($current_equity - $starting_equity) / $starting_equity < -abs($leave_percent)) {
        echo "\n\nEquity is now below the leaving threshold. Quitting bye bye!";
        break;
      }
      $processes = $initiator->prepare($run);
      $timing->waitTillRun($run);

      $start = microtime(true);
      if (!isset($run['dir']))
        $run['dir'] = null;
      $stocks = $cache->get('stocks', [$run['sid'], 'short', $run['number_of_trades'], null, null, $run['dir'], $run['skip'] ?? 0]);

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
          'dir' => $run['dir'] ?? null,
          'skip' => $run['skip'] ?? 0
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
