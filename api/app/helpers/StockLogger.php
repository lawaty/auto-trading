<?php

class StockLogger
{
  public static function logStock(string $which, string $operation, array $order)
  {
    $json = json_decode(file_get_contents(JSONS_DIR . "/$which.json"), true);
    $json['stocks'][] = [
      ...$order,
      'operation' => $operation
    ];
    file_put_contents(JSONS_DIR . "/$which.json", json_encode($json));
  }

  public static function emptyStock(string $which)
  {
    $json = json_decode(file_get_contents(JSONS_DIR . "/$which.json"), true);
    $json['stocks'] = [];
    file_put_contents(JSONS_DIR . "/$which.json", json_encode($json));
  }
}
