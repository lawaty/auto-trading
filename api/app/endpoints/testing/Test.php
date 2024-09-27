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
    $config = Config::getInst();
    $config->refresh();
    $bell = new Ndate($config['globals']['close_time']);
    var_dump(new Ndate);
    var_dump($bell);
    var_dump((new Ndate)->minutesUntil($bell));
    return new Response;
  }
}
