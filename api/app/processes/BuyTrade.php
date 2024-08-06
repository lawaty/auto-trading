<?php

const CLOSING_TOLERANCE = 30; // mins

require_once __DIR__ . "../../../autoload.php";

$args = json_decode($argv[1], true);
$buyer = new DefensiveTrader(new TradeStation('buy'), $args['symbol'], 'Market', 'BUY');
$buyer->setBudget($args['budget']);
try{
  $buyer->run();
}
catch (InsufficientMoney $e) {
  echo "Couldn't buy any stocks. Leaving...\n";
  exit;
}

$args['stock'] = $buyer->getStock();

$sequence = new Sequence($args, 'buy');
$sequence->run();
