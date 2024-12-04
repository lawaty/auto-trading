<?php

require_once __DIR__ . "../../../autoload.php";

$streamer = new QuoteStreamer("AAPL", QuoteStreamer::FINNHUB_URI);
$streamer->subscribe();

$fetch_inst = microtime(true);
$stock_price = null;
$current_increase = 0;
while (true) {
  $data = $streamer->receive();
  if($data['type'] == 'ping'){
    echo "Pinged\n";
    continue;
  }

  $time_taken = microtime(true) - $fetch_inst;
  $fetch_inst = microtime(true);
  $data['price'] = $data['data'][0]['p'];

  $new_increase =  $stock_price ? (($data['price'] - $stock_price) / $stock_price) * 100 : 0;
  $drop = abs($new_increase - $current_increase);
  echo "Stock Increase: $new_increase with latency $time_taken secs and drop $drop\n";

  $stock_price = $data['price'];
  $current_increase = $new_increase;
}