<?php

class TradeMonitor
{
  private static $lockFilePointer;
  public function __construct() {}

  private function acquire(): void
  {
    $lockFile = TMP_DIR . '/currently_trading.lock';
    $fp = fopen($lockFile, 'w');
    if (!$fp || !flock($fp, LOCK_EX)) {
      throw new \RuntimeException('Unable to acquire lock.');
    }

    $this::$lockFilePointer = $fp;
  }

  private function release(): void
  {
    if ($this::$lockFilePointer) {
      flock($this::$lockFilePointer, LOCK_UN);
      fclose($this::$lockFilePointer);
    }
  }

  private function load(): array
  {
    return json_decode(file_get_contents(JSONS_DIR . '/currently_trading.json'), true);
  }

  private function set(array $trades): void
  {
    file_put_contents(JSONS_DIR . '/currently_trading.json', json_encode($trades));
  }

  public function add(array $trade): void
  {
    $this->acquire();

    $currently_trading = $this->load();

    $pid = getmypid();
    if (isset($currently_trading[$pid]))
      $currently_trading[$pid][$trade['symbol']] = $trade;
    else
      $currently_trading[$pid] = [$trade['symbol'] => $trade];

    $this->set($currently_trading);

    $this->release();
  }

  public function remove(string $target): void
  {
    $this->acquire();
    $currently_trading = $this->load();
    foreach ($currently_trading as $pid => &$trades) {
      if (isset($trades[$target])) {
        unset($trades[$target]);
        if (empty($trades))
          unset($currently_trading[$pid]);
        break;
      }
    }

    $this->set($currently_trading);
    $this->release();
  }

  public function update(array $trade): void
  {
    $this->remove($trade['symbol']);
    $this->add($trade);
  }

  public function empty(): void
  {
    $this->acquire();
    $this->set([]);
    $this->release();
  }

  public function get(string $target_symbol): ?array
  {
    $this->acquire();

    foreach ($this->load() as $pid => $trades) {
      foreach ($trades as $symbol => $trade) {
        if ($symbol == $target_symbol) {
          $this->release();
          return $trade;
        }
      }
    }

    $this->release();
    return null;
  }

  public function getAllByPID(int $pid): array
  {
    $this->acquire();

    $trades = $this->load()[$pid] ?? [];

    $this->release();

    return $trades;
  }

  public function getAll(): array
  {
    return array_merge(...array_values($this->load()));
  }

  public function getAllSymbols(): array
  {
    return array_keys(array_merge(...array_values($this->load())));
  }
}
