<?php


class Shutdown extends Endpoint
{
    public function __construct()
    {
        $this->init([
            'pid' => [true, Regex::INT]
        ], $_GET);
    }
    public function handle(): Response
    {
        try {
            $process = Process::getProcess($this->request['pid']);
        } catch (ProcessNotFound $e) {
            return new Response("Process with PID: " . $this->request['pid'] . " was not found", 404);
        } catch (Exception | Error $e) {
            return new Response("An error has occured", 500);
        }

        try {
            $process->shutdown();
        } catch (ProcessNotFound $e) {
            return new Response("Process with PID: " . $this->request['pid'] . " was not found", 404);
        }
        return new Response("Process killed successfully",200);
    }
}
