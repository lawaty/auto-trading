<?php

class SetLossSettings extends Endpoint
{
  public function __construct()
  {
    $this->init([
      'buy' => [
        true,
        [
          'stop_loss_percent' => [false, MyRegex::FLOAT],
          'sequences' => [true, [
            'percent' => [true, Regex::generic(1, 200)],
            'wait_time' => [true, Regex::generic(1, 200)]
          ], true],
          'sid' => [true, Regex::INT]
        ]
      ],
      'short' => [
        true,
        [
          'stop_loss_percent' => [false, MyRegex::FLOAT],
          'sequences' => [true, [
            'percent' => [true, Regex::generic(1, 200)],
            'wait_time' => [true, Regex::generic(1, 200)]
          ], true],
          'sid' => [true, Regex::INT]
        ]
      ]
    ], $_POST);
  }

  public function handle(): Response
  {
    $params = (new Config)->toArray();
    $params['buy-loss'] = $this->request['buy'];
    $params['short-loss'] = $this->request['short'];

    if (!isset($params['buy-loss']['stop_loss_percent']))
      $params['buy-loss']['stop_loss_percent'] = 0.1;

    if (!isset($params['short-loss']['stop_loss_percent']))
      $params['short-loss']['stop_loss_percent'] = 0.1;

    if (file_put_contents(JSONS_DIR . '/params.json', json_encode($params)))
      return new Response;
    else
      return new Response('', 500);
  }
}
