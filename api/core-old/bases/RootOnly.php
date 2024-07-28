<?php

abstract class RootOnly extends Endpoint
{
  protected function init(array $expect, array $request): void
  {
    parent::init(
      [...$expect, 'Authorization' => [true, Regex::JWT_BEARER]],
      [...$request, 'Authorization' => getallheaders()['Authorization'] ?? '']
    );

    $this->prehandles[] = function () {
      $token = explode('Bearer ', $this->request['Authorization'])[1];
      if ((!$data = Authenticator::decode($token)) || !isset($data->root))
        return new Response('Unauthorized', 401);

      return null;
    };
  }
}
