<?php

class SetGlobalParams extends Endpoint
{
    private string $params_file;
    public function __construct()
    {
        $this->params_file = JSONS_DIR . "/params.json";
        $this->init([
            'closed_days' => [false, Regex::generic(1, 300)],
            'market_opens_at' => [false, Regex::TIME],
            'market_closes_at' => [false, Regex::TIME],
            'account_id' => [false, Regex::generic(1, 200)],
            'money' => [false, Regex::ANY],
            'keys' => [false, Regex::generic(1, 300)],
            'secrets' => [false, Regex::generic(1, 300)],
            'is_full_power' => [false, Regex::INT],
            'is_live' => [false, Regex::INT]
        ], $_POST);
    }

    public function handle(): Response
    {
        if (isset($this->request['keys']) && isset($this->request['secrets'])) {
            $this->request['keys'] = explode(',', $this->request['keys']);
            $this->request['secrets'] = explode(',', $this->request['secrets']);
            $this->request['APIs'] = [];
            foreach ($this->request['keys'] as $i => $key)
                $this->request['APIs'][] = [
                    'key' => $key,
                    'secret' => $this->request['secrets'][$i]
                ];

            unset($this->request['keys']);
            unset($this->request['secrets']);
        }
        $this->request['closed_days'] = explode(',', $this->request['closed_days']);
        $json_data = file_get_contents($this->params_file);
        try {
            $decoded_data = json_decode($json_data);
            $global_params = $decoded_data->globals;
        } catch (Exception | Error $e) {
            var_dump($e);
            return new Response("An error has occurred", 500);
        }

        foreach ($this->request as $global_param_name => $global_param_value) {
            $global_params->$global_param_name = $global_param_value;
        }

        $new_json_data = json_encode($decoded_data, JSON_PRETTY_PRINT);
        if (file_put_contents($this->params_file, $new_json_data) === false) {
            $error_message = error_get_last()['message'];
            return new Response("Failed to save global configurations: $error_message", 500);
        }


        return new Response("Changes saved successfully", 200);
    }
}
