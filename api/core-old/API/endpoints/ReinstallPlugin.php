<?php

class ReinstallPlugin extends RootOnly
{
  public function __construct()
  {
    $this->init([
      'name' => [true, Regex::generic(1, 200)]
    ], $_POST);
  }

  public function handle(): Response
  {
    if(!is_dir(PLUGINS_DIR . '/' . $this->request['name']))
      return new Response("Plugin Not Installed", 404);

    require PLUGINS_DIR . '/' . $this->request['name'] . '/install.php';
    return new Response;
  }
}
