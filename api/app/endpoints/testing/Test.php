<?php

require_once MODEL_DIR . "/mocks/DemoTradeStation.php";

class Test extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $cache = ArteCache::getInst();
    $path = $cache->export(5);

    for($i = 0; $i < 7; $i++)
      if($cache->import($path))
        echo "Imported <br>";

    return new Response;
  }
}
