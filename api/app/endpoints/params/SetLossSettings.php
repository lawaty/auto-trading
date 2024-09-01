<?php

class SetLossSettings extends Endpoint
{
  public function __construct()
  {
    $this->init([
      'buy' => [
        true,
        [
          'stop_loss_percent' => [true, MyRegex::FLOAT],
          'sequences' => [true, [
            'percent' => [true, Regex::generic(1, 200)],
            'wait_time' => [true, Regex::generic(1, 200)]
          ], true]
        ]
      ],
      'short' => [
        true,
        [
          'stop_loss_percent' => [true, MyRegex::FLOAT],
          'sequences' => [true, [
            'percent' => [true, Regex::generic(1, 200)],
            'wait_time' => [true, Regex::generic(1, 200)]
          ], true]
        ]
      ]
    ], $_POST);
  }

  public function handle(): Response
  {
    $params = (new Config)->toArray();
    $params['buy-loss'] = $this->request['buy'];
    $params['short-loss'] = $this->request['short'];

    if (file_put_contents(JSONS_DIR . '/params.json', json_encode($params)))
      return new Response;
    else
      return new Response('', 500);
  }
}
