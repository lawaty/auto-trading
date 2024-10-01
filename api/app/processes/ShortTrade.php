<?php

require_once __DIR__ . "../../../autoload.php";

$args = json_decode($argv[1], true);

if (isset($args['wait'])) {
  echo "Waiting {$args['wait']} before trading\n";
  sleep($args['wait']);
}

$start = microtime(true);
// Preparing Cache
$cache = ArteCache::getInst();
$tradestation = new TradeStation('short');
if (isset($args['cache'])) {
  echo "Loading Cached Data\n";
  $cache->import($args['cache']);
}

echo "Started trading for {$args['symbol']} at " . (new Ndate)->format(Ndate::DATE_TIME) . "\n";

$stock = $cache->get('stock', [$args['symbol']]);
$stock['symbol'] = $args['symbol'];

$buyer = new DefensiveTrader($tradestation, $stock, 'Market', 'SELLSHORT', $args['sid']);
$buyer->setBudget($args['budget']);
if (isset($args['max_quantity']))
  $buyer->setMaxQuantity($args['max_quantity']);

$time_taken = microtime(true) - $start;
echo "$time_taken secs taken to initialize the process.\n";

$start = microtime(true);

while (true) {
  try {
    $buyer->run();
    $time_taken = microtime(true) - $start;
    echo "$time_taken secs taken to order in tradestation.\n";
    break;
  } catch (InsufficientMoney $e) {
    echo "Couldn't sellshort any stocks. Leaving...\n";
    $time_taken = microtime(true) - $start;
    echo "$time_taken secs taken to order in tradestation.\n";
    exit;
  } catch (OrderFailed $e) {
    echo "Setting Order Failed: {$e->getMessage()}\n";
    $time_taken = microtime(true) - $start;
    echo "$time_taken secs taken to order in tradestation.\n";
    exit;
  } catch (Rejected $e) {
    $buyer::addtoCurrentlyTrading($symbol);
    $buyer->changeStock();
  }
}

$args['stock'] = $buyer->getStock();

echo "Started Sequence for {$args['symbol']} at " . (new Ndate)->format(Ndate::DATE_TIME) . "\n";

$sequence = new Sequence($args, 'short');
$sequence->run();

if (!isset($args['no-revert']) && $sequence->getStatus() == Sequence::STOPLOSS) {
  new StockMonitor;
  $stock = $cache->get('stocks', [$args['sid'], 'short', 1, [$args['symbol']], null, $args['dir']])[0];
  $log_dir = APP_DIR . "/processes/logs/short/" . (new Ndate)->format();
  if (!is_dir($log_dir))
    mkdir($log_dir);

  $j = 1;
  while (file_exists($log_dir . '/' . $stock['symbol'] . "(stoploss)-$j.log"))
    $j++;

  $log_file = $log_dir . '/' . $stock['symbol'] . "(stoploss)-$j.log";
  touch($log_file);

  $process = new Process("ShortTrade", $log_file);
  $process_args = $args;
  unset($process_args['cache']);
  $process_args['symbol'] = $stock['symbol'];
  $process_args['budget'] = $buyer->getFilledPrice();
  $process_args['no-revert'] = true;
  $process->passArgs($process_args, true);
  $process->run(Process::BACKGROUND);
}

echo "Cache Loading Logs:\n";
prettyPrint($cache->logs);
echo "Quitting, Bye!\n";
