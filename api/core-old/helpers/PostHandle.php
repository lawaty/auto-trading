<?php

class PostHandle
{
  private static array $callbacks = [];

  public static function add(string $callback, ...$params)
  {
    self::$callbacks[] = [$callback, $params];
  }

  public static function run()
  {
    foreach (self::$callbacks as $temp) {
      $callback = $temp[0];
      $params = $temp[1];

      try {
        $callback(...$params);
      } catch (Exception $e) {
      }
    }
  }
}
