<?php

class login extends Endpoint
{
    public function __construct()
    {
        $this->init([
            'state' => [true, Regex::ANY],
            'client' => [true, Regex::ANY],
            'protocol' => [true, Regex::ANY],
            'response_type' => [true, Regex::ANY],
            'redirect_uri' => [true, Regex::ANY],
            'scope' => [true, Regex::ANY],
        ], $_GET);
    }

    public function handle(): Response
    {
        $this->request['audience'] = 'https://api.tradestation.com';
        return new Response("ANA HENA");
    }
}
