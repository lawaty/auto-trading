<?php

const CLOSING_TOLERANCE = 30; // mins

require_once __DIR__ . "../../../autoload.php";

$args = json_decode($argv[1], true);

echo "Strated trading for {$args['symbol']} at " . (new Ndate)->format(Ndate::DATE_TIME) . '\n';

$buyer = new DefensiveTrader(new TradeStation('buy'), $args['symbol'], 'Market', 'BUY');
$buyer->setBudget($args['budget']);
if (isset($args['max_quantity']))
  $buyer->setMaxQuantity($args['max_quantity']);

try {
  $buyer->run();
} catch (InsufficientMoney $e) {
  echo "Couldn't buy any stocks. Leaving...\n";
  @unlink(ROOT_DIR . "/tmp/{$args['symbol']}.closed");
  exit;
} catch (OrderFailed $e) {
  echo "Setting Order Failed: {$e->getMessage()}\n";
  @unlink(ROOT_DIR . "/tmp/{$args['symbol']}.closed");
  exit;
}

$args['stock'] = $buyer->getStock();

$sequence = new Sequence($args, 'buy');
$sequence->run();

if ($sequence->getStatus() == Sequence::STOPLOSS) {
  $stock = $sequence->getStock();
  $log_dir = APP_DIR . "/processes/logs/short/" . (new Ndate)->format();
  if (!is_dir($log_dir))
    mkdir($log_dir);

  $j = 1;
  while (file_exists($log_dir . '/' . $stock['symbol'] . "-$j.log"))
    $j++;

  $log_file = $log_dir . '/' . $stock['symbol'] . "(stoploss)-$j.log";
  file_put_contents($log_file, "");

  $process = new Process("ShortTrade", $log_file);
  $args = (new Config)->toArray()['buy-loss'];
  $args['budget'] = (new TradeStation('short'))->getBuyingPower();
  $args['symbol'] = $stock['symbol'];
  $args['max_quantity'] = $buyer->getQuantity() * 2;
  $process->passArgs($args, true);
  $process->run(Process::BACKGROUND);
}

echo "Quitting, Bye!\n";
