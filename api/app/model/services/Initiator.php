<?php

class Initiator
{
  private string $which;

  private TradeStation $trade_station;
  private Config $config;
  private ArteCache $cache;

  public function __construct(string $type)
  {
    $this->which = $type;
    $this->trade_station = new TradeStation($type);

    // Registering caching
    $this->cache = ArteCache::getInst();
    $this->trade_station->installCache();

    $this->config = Config::getInst();
  }

  public function refresh()
  {
    $this->config->refresh();

    $bell = new Ndate($this->config['globals']['until']);
    $till_bell = (new Ndate)->minutesUntil($bell) + 1;
  }

  public function prepare(array $run): array
  {
    echo "Preparing Bot Trader... at " . (new Ndate)->format(Ndate::DATE_TIME) . "\n";

    $this->config->refresh();
    StockLogger::emptyStock(ucfirst($this->which));

    new StockMonitor; // installing cache
    $this->cache->get('tradestation_access_token', [], true);
    $this->cache->get('stockmonitor_cookie', [], true);
    $this->cache->get('conds', [$run['sid']], true);
    $this->cache->get('runId', [$run['sid']], true);
    $this->cache->get('buying_power', [], true);

    // Preparing logs
    $log_dir = APP_DIR . "/processes/logs/{$this->which}/" . (new Ndate)->format();
    if (!is_dir($log_dir))
      mkdir($log_dir);

    for ($i = 0; $i < $run['number_of_trades']; $i++) {
      $j = 1;
      while (file_exists($log_dir . '/' . "{$this->which}-$j.log"))
        $j++;

      $log_file = $log_dir . '/' . "{$this->which}-$j.log";
      file_put_contents($log_file, "");
      $process = new Process(ucfirst($this->which) . "Trade", $log_file);
      $processes[] = $process;
    }

    prettyPrint($this->cache->logs);
    $this->cache->logs = [];

    echo "Finished Preparing at " . (new Ndate)->format(Ndate::DATE_TIME) . " ...\n";

    return $processes;
  }

  public function getProfit(): float {
    return $this->trade_station->getProfit();
  }
}
