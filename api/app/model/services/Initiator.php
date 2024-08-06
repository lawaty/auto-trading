<?php

class Initiator
{
  private string $which;
  private string $other;

  private TradeStation $trade_station;
  private StockMonitor $stock_monitor;
  private Config $config;
  private string $market_status;
  private bool $run_before = false;
  private float $buying_power;

  public function __construct(string $type)
  {
    $this->which = $type;
    $this->other = $this->which == 'buy' ? 'short' : 'buy';
    $this->trade_station = new TradeStation($type);
    $this->stock_monitor = new StockMonitor;
    $this->config = new Config;
  }

  public function refresh()
  {
    $this->config->refresh();

    $this->market_status = $this->config['globals']['status'];
    $bell = new Ndate($this->config['globals']['until']);
    $till_bell = (new Ndate)->minutesUntil($bell) + 1;

    if ($till_bell < 0)
      $this->market_status = $this->config['globals']['status'] == 'Open' ? 'Closed' : 'Open';
  }

  public function marketOpen()
  {
    return !$this->run_before && $this->market_status == 'Open';
  }

  public function prepare()
  {
    StockLogger::emptyStock(ucfirst($this->which));
    if ($this->config['globals']['money_format'] == 1) // Full buying power
      $this->buying_power = $this->trade_station->getBuyingPower();
    else
      $this->buying_power = $this->config['global']['money'];
  }

  public function instantiateRuns()
  {
    $this->run_before = true;
    $start = (new Ndate)->format(Ndate::DATE_TIME);
    $processes = [];

    foreach ($this->config[$this->which] as $i => $run) {
      $run['trade_after'] -= INIT_TIME;

      $now = new Ndate;
      $run_start = new Ndate($start);
      $run_start->addSeconds($run['trade_after'] * 60);
      $wait = max($now->secondsUntil($run_start), 0);
      $mins = $wait / 60;
      echo "{$this->which} run $i starting in $mins mins\n";
      sleep($wait);

      echo "Preparing trades and starting in a while\n";

      $budget = $this->buying_power / $run['number_of_trades'];
      if (count($this->config[$this->other]))
        $budget /= 2;

      $stocks = $this->stock_monitor->getStocks($run['sid'], $this->which, $run['number_of_trades']);

      foreach ($stocks as $i => $stock) {
        $log_dir = APP_DIR . "/processes/logs/{$this->which}/" . (new Ndate)->format();
        if (!is_dir($log_dir))
          mkdir($log_dir);

        $j = 1;
        while (file_exists($log_dir . '/' . $stock['symbol'] . "-$j.log"))
          $j++;

        $log_file = $log_dir . '/' . $stock['symbol'] . "-$j.log";
        file_put_contents($log_file, "");

        $run['budget'] = $budget;
        $run['symbol'] = $stock['symbol'];
        $process = new Process(ucfirst($this->which) . "Trade", $log_file);
        $process->passArgs($run, true);
        $processes[] = $process;
      }

      sleep(10); // giving time for other initiators to calculate their budgets

      foreach ($processes as $process) $process->run(Process::BACKGROUND);
    }
  }

  private function restartTomorrow()
  {
    $tomorrow_1am = (new Ndate('01:00:00'))->addDays(1);
    $till_tomorrow = (new Ndate)->minutesUntil($tomorrow_1am) + 1;
    echo "Waiting till the next day at 1am to restart the process: $till_tomorrow mins\n";
    sleep(60 * max($till_tomorrow, 1));

    $new_process = new Process("apply" . ucfirst($this->which) . "Strategies");
    $new_process->setLogger(APP_DIR . '/processes/logs/' . ucfirst($this->which) . '-' . (new Ndate)->format() . '.log');
    if ($new_process->run(Process::BACKGROUND)) {
      $json = json_decode(file_get_contents(JSONS_DIR . "/" . ucfirst($this->which) . ".json"), true);
      $json['pid'] = $new_process->getPID();
      file_put_contents(JSONS_DIR . "/" . ucfirst($this->which) . ".json", json_encode($json));
      exit;
    }
  }

  public function sleep(): void
  {
    $bell = new Ndate($this->config['globals']['until']);
    $till_bell = (new Ndate)->minutesUntil($bell);

    if (!$this->run_before && $this->market_status == 'Open') {
      echo "Trading is running the background. Waiting till the market closes after $till_bell mins";
      sleep(max($till_bell, 1) * 60); // wait till closes again
    } else {
      $tomorrow_1am = (new Ndate('01:00:00'))->addDays(1);
      $till_tomorrow = (new Ndate)->minutesUntil($tomorrow_1am) + 1;
      if ($till_tomorrow < $till_bell)
        $this->restartTomorrow();
      else {
        echo "Waiting till the next market opening: $till_bell mins\n";
        sleep($till_bell * 60);
      }
    }
  }
}
