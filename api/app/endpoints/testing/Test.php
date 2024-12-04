<?php

class Test extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $logs = fopen(LOG_DIR . '/processes/2024-11-22/StreamTest-7.log', 'r');

    $prev_increase = 0;
    $max_drop = 0;
    $freq = [];
    while (($line = fgets($logs)) !== false) {
      if (!preg_match('/Stock Increase:\s+(-?\d+(\.\d+)?\s)/', $line, $matches)) {
        echo "Weird Line; $line<br>";
        continue;
      }

      $drop = round(abs($matches[1] - $prev_increase), 2);
      if (isset($freq[(string)$drop]))
        $freq[(string)$drop]++;
      else
        $freq[(string)$drop] = 1;

      $prev_increase = $matches[1];
      $max_drop = max($max_drop, $drop);
      echo "Increase: $prev_increase, Drop: {$drop}<br>";
    }

    echo "Max Drop: $max_drop";
    prettyPrint($freq);

    fclose($logs);
    return new Response();
  }
}
