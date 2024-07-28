<?php

class Config implements ArrayAccess
{
  private array $config;

  public function __construct()
  {
    $this->refresh();
  }

  public function refresh()
  {
    while(true) {
      $json_data = file_get_contents(JSONS_DIR . "/params.json");
      $data = json_decode($json_data, true);

      if($data)
        break;
    }

    $this->config = $data;
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
}