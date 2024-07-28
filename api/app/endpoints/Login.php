<?php

class Login extends Endpoint
{
  public function __construct()
  {
    $this->init([
      'username' => [true, Regex::LOGIN],
      'password' => [true, Regex::LOGIN]
    ], $_POST);
  }

  public function handle(): Response
  {
    if ($this->request['username'] == $_ENV['username'] && $this->request['password'] == $_ENV['password'])
      return new Response(Authenticator::encode(['token' => time()]));
    else return new Response('', 401);
  }
}
