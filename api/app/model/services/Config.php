<?php

class Config implements ArrayAccess
{
  const JSONS_DIR = MODEL_DIR . "/jsons";
  private array $config;
  private static Config $inst;

  public function __construct()
  {
    if (!isset(self::$inst))
      self::$inst = $this;

    $this->refresh();
  }

  public static function getInst(): Config
  {
    if (!isset(self::$inst))
      self::$inst = new Config;

    return self::$inst;
  }

  public function refresh()
  {
    while (true) {
      $json_data = file_get_contents(self::JSONS_DIR . "/params.json");
      $data = json_decode($json_data, true);

      if ($data)
        break;
    }

    $this->config = $data;

    # TODO: This validation needs to be implemented in the setParams endpoint

    foreach($this->config['buy'] as &$run) {
      $run['stop_loss_percent'] = -abs($run['stop_loss_percent']);
      foreach($run['sequences'] as &$sequence) {
        $sequence['trigger'] = abs($sequence['trigger']);
        $sequence['trail'] = abs($sequence['trail']);
      }
    }

    foreach($this->config['short'] as &$run) {
      $run['stop_loss_percent'] = abs($run['stop_loss_percent']);

      foreach($run['sequences'] as &$sequence) {
        $sequence['trigger'] = - abs($sequence['trigger']);
        $sequence['trail'] = - abs($sequence['trail']);
      }
    }
  }

  public function offsetExists(mixed $offset): bool
  {
    return isset($this->config[$offset]);
  }

  public function offsetGet(mixed $offset): mixed
  {
    return $this->config[$offset];
  }

  public function offsetSet(mixed $offset, mixed $value): void
  {
    $this->config[$offset] = $value;
  }

  public function offsetUnset(mixed $offset): void
  {
    unset($this->config[$offset]);
  }

  public function toArray(): array
  {
    return $this->config;
  }

  public function save(): bool
  {
    return file_put_contents(JSONS_DIR . "/params.json", json_encode($this->config));
  }
}
