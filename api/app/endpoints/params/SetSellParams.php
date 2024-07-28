<?php

class SetSellParams extends Endpoint
{
    private string $params_file;
    public function __construct()
    {
        $this->params_file = JSONS_DIR . "/params.json";

        $this->init([
            'use' => [false, Regex::INT],
            'number_of_trades' => [false, Regex::INT],
            'trade_after' => [false, Regex::separated(',')],
            'percent' => [false, Regex::generic(1, 200)],
            'wait_time' => [false, Regex::generic(1, 200)],
            'stop_loss_percent' => [false, Regex::ANY],
            'filter_id' => [false, Regex::INT]
        ], $_POST);
    }

    public function handle(): Response
    {
        if (isset($this->request['percent']) && isset($this->request['wait_time'])) {
            $this->request['percent'] = explode(',', $this->request['percent']);
            $this->request['wait_time'] = explode(',', $this->request['wait_time']);
            $this->request['sequences'] = [];
            foreach ($this->request['percent'] as $i => $percent)
                $this->request['sequences'][] = [
                    'percent' => $percent,
                    'wait_time' => $this->request['wait_time'][$i]
                ];

            unset($this->request['percent']);
            unset($this->request['wait_time']);
        }

        $this->request['trade_after'] = explode(',', $this->request['trade_after']);

        $json_data = file_get_contents($this->params_file);
        try {
            $decoded_data = json_decode($json_data);
            $sell_params = $decoded_data->short;
        } catch (Exception | Error $e) {
            var_dump($e);
            return new Response("An error has occurred", 500);
        }

        foreach ($this->request as $sell_param_name => $sell_param_value) {
            $sell_params->$sell_param_name = $sell_param_value;
        }

        $new_json_data = json_encode($decoded_data, JSON_PRETTY_PRINT);
        if (file_put_contents($this->params_file, $new_json_data) === false) {
            $error_message = error_get_last()['message'];
            return new Response("Failed to save sell configurations: $error_message", 500);
        }

        return new Response("Changes saved successfully", 200);
    }
}
