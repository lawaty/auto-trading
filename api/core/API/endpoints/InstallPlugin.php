<?php

class InstallPlugin extends RootOnly
{
  public function __construct()
  {
    $this->init([
      'name' => [true, Regex::separated('/')]
    ], $_REQUEST);
  }

  public function handle(): Response
  {
    if(file_exists(PLUGINS_DIR . '/' . str_replace('/', '-', $this->request['name'])))
      return new Response('Plugin Already Installed', 409);

    $ch = curl_init('https://arte-store.drolez-apps.cloud/plugins/download?name=' . $this->request['name']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if ($errno = curl_errno($ch))
      return new Response("Error [$errno] Curling Arte Store Failed: " . curl_error($ch));

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check for cURL errors
    switch ($httpCode) {
      case 200:
        $plugin_dir = PLUGINS_DIR . '/' . str_replace('/', '-', $this->request['name']);
        $plugin_zip = $plugin_dir . '.zip';
        file_put_contents($plugin_zip, $response);
        chmod($plugin_zip, 0777);
        mkdir($plugin_dir);

        $zip = new ZipArchive();
        if ($zip->open($plugin_zip) === true) {
          // Extract the contents
          $zip->extractTo($plugin_dir);
          $zip->close();
          chmodRecursive($plugin_dir, 0777);
          require $plugin_dir . '/autoload.php';
          require $plugin_dir . '/install.php';
          unlink($plugin_zip);
          return new Response;
        } else {
          return new Response("Failed to open the downloaded zip file", 500);
        }

      case 404:
        return new Response('Plugin Not Published On Store', 404);

      default:
        return new Response("Unexpected Error Occured", 500);
    }
  }
}
