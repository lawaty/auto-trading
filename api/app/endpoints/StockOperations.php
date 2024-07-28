<?php

class StockOperations extends Authenticated
{
  public function __construct()
  {
    $this->init([
      'which' => [true, Regex::ANY]
    ], $_GET);
  }

  public function handle(): Response
  {
    return new Response(
      json_decode(file_get_contents(JSONS_DIR. "/".$this->request['which'].".json"), true)['stocks']
    );
  }
}