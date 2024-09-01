<?php

class Initiator
{
  private string $which;

  private TradeStation $trade_station;
  private StockMonitor $stock_monitor;
  private Config $config;
  private string $market_status;
  private bool $run_before = false;
  private float $buying_power;

  public function __construct(string $type)
  {
    $this->which = $type;
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
    $is_open = !$this->run_before && $this->market_status == 'Open';
    echo $is_open ? "Market is open to trading :)\n" : "Market Closed :(\n";
    return $is_open;
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
    $market_start = $this->config['globals']['open_time'];

    foreach ($this->config[$this->which] as $i => $run) {
      $run['trade_after'] -= INIT_TIME;

      $now = new Ndate;
      $run_start = new Ndate($market_start);
      $run_start->addSeconds($run['trade_after'] * 60);
      $till_run = $now->secondsUntil($run_start);
      if ($till_run < -180)
        continue;

      $wait = max($till_run, 0);
      countdown("{$this->which} run " . ($i + 1), $wait);

      echo "Preparing trades and starting in a while\n";
      $stocks = $this->stock_monitor->getStocks($run['sid'], $this->which, $run['number_of_trades']);

      $this->buying_power = $this->trade_station->getBuyingPower();
      $budget = $run['buying_power_percent'] * $this->buying_power;
      $budget /= count($stocks);

      $processes = [];
      file_put_contents(ROOT_DIR . "/tmp/excluded.json", "");
      foreach ($stocks as $stock) {
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

      sleep(MIN_WAIT * 2); // giving time for other initiators to calculate their budgets

      foreach ($processes as $process) $process->run(Process::BACKGROUND);
    }
  }

  private function restartTomorrow()
  {
    $tomorrow_1am = (new Ndate('01:00:00'))->addDays(1);
    $till_tomorrow = (new Ndate)->minutesUntil($tomorrow_1am) + 1;
    countdown("the next day at 1am", max(60 * $till_tomorrow, MIN_WAIT));

    $new_process = new Process("apply" . ucfirst($this->which) . "Strategies");
    $new_process->setLogger(APP_DIR . '/processes/logs/' . ucfirst($this->which) . '-' . (new Ndate)->format() . '.log');
    if ($new_process->run(Process::BACKGROUND))
      exit;
  }

  public function sleep(): void
  {
    $bell = new Ndate($this->config['globals']['until']);
    $till_bell = (new Ndate)->minutesUntil($bell);

    if (!$this->run_before && $this->market_status == 'Open') {
      echo "Trading is running in the background. ";
      countdown("Market Closing", max($till_bell * 60, MIN_WAIT));
    } else {
      $tomorrow_1am = (new Ndate('01:00:00'))->addDays(1);
      $till_tomorrow = (new Ndate)->minutesUntil($tomorrow_1am) + 1;
      if ($till_tomorrow < $till_bell)
        $this->restartTomorrow();
      else
        countdown("Market Opening", max($till_bell * 60, MIN_WAIT));
    }
  }
}
