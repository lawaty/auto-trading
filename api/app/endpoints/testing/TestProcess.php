<?php

class TestProcess extends Endpoint
{
    public function __construct()
    {
        $this->init([], $_GET);
    }
    public function handle(): Response
    {
        $process = new Process("applyShortStrategies");
        try {
            $response = $process->run(Process::BACKGROUND);
            $process->getState();
        } catch (Exception | Error $e) {
            var_dump($e);
        }
        return new Response($response);
    }
}
