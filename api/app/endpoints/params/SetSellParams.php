<?php

class SetSellParams extends Endpoint
{
    private string $params_file;
    public function __construct()
    {
        $this->params_file = JSONS_DIR . "/params.json";

        $this->init([
            'runs' => [false, [
                'number_of_trades' => [true, Regex::INT],
                'buying_power_percent' => [true, MyRegex::FLOAT],
                'trade_after' => [true, Regex::INT],
                'stop_loss_percent' => [true, Regex::ANY],
                'sequences' => [true, [
                    'percent' => [true, Regex::generic(1, 200)],
                    'wait_time' => [true, Regex::generic(1, 200)],
                ], true],
                'sid' => [true, Regex::INT],
                'dir' => [true, "/^(ASC|DESC)$/"]
            ], true],
        ], $_POST);
    }

    public function handle(): Response
    {
        $json_data = json_decode(file_get_contents($this->params_file), true);
        $json_data['short'] = $this->request['runs'] ?? [];

        $new_json_data = json_encode($json_data, JSON_PRETTY_PRINT);
        if (file_put_contents($this->params_file, $new_json_data) === false) {
            $error_message = error_get_last()['message'];
            return new Response("Failed to save short configurations: $error_message", 500);
        }

        return new Response("Changes saved successfully", 200);
    }
}
