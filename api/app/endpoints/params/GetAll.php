<?php

class GetAll extends Authenticated
{
  public function __construct()
  {
    $this->init([
      'which' => [false, Regex::ANY]
    ], $_GET);
  }

  public function handle(): Response
  {
    $all_params = json_decode(file_get_contents(JSONS_DIR . '/params.json'), true);
    $tradestation = new TradeStation('buy');
    $tradestation->refreshToken();
    try {
      $balance = $tradestation->getBalance();
    } catch (InvalidAccountID $e) {
      $balance = null;
    }

    if (!isset($this->request['which']))
      return new Response([...$all_params, 'balance' => $balance]);
    else
      return new Response([
        'runs' => $all_params[$this->request['which']],
        'pid' => json_decode(file_get_contents(JSONS_DIR . '/' . ucfirst($this->request['which']) . '.json'), true)['pid']
      ]);
  }
}
