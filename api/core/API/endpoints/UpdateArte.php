<?php

class UpdateArte extends RootOnly
{
  public function __construct()
  {
    $this->init([], $_REQUEST);
  }

  public function handle(): Response
  {
    $curl = new ArteCurl('https://arte-store.drolez-apps.cloud/downloads/arte-core');
    $curl->setHeaders([
      'Authorization' => 'Bearer ' . $_ENV['License']
    ]);

    try {
      $response = $curl->send('GET');
    } catch (Exception $e) {
      return new Response("Error Curling Arte Store: " . $curl->getError());
    }

    $httpCode = $response->getCode();

    // Check for cURL errors
    switch ($httpCode) {
      case 200:
        // Creating backup
        if (is_dir(CORE_DIR . '-old') && !deleteDirectory(CORE_DIR . '-old'))
          return new Response('Couldn\'t create backup before update. Might be permission error', 500);

        if (!rename(CORE_DIR, CORE_DIR . '-old'))
          return new Response('Couldn\'t create backup before update. Might be permission error', 500);

        if (!mkdir(CORE_DIR) || !chmod(CORE_DIR, 0777)) {
          rename(CORE_DIR . '-old', CORE_DIR);
          return new Response('Couldn\'t create core directory with 777 permission');
        }

        $file = CORE_DIR . '/arte-core.zip';
        file_put_contents($file, $response->getBody());
        chmod($file, 0777);

        $zip = new ZipArchive();
        $zip_status = $zip->open($file);
        if ($zip_status === true) {
          // Extract the contents
          if ($zip->extractTo(CORE_DIR))
            $zip->close();
          else {
            deleteDirectory(CORE_DIR);
            rename(CORE_DIR . '-old', CORE_DIR);
            return new Response('Couldn\'t extract to core directory, returning everything as it was.', 500);
          }

          chmodRecursive(CORE_DIR, 0777);
          $root_files = scandir(CORE_DIR . '/root');
          $all_root_files_updated = true;
          $info = [];
          foreach ($root_files as $a_root_file) {
            if ($a_root_file == '..' || $a_root_file == '.')
              continue;

            if (!rename(CORE_DIR . '/root/' . $a_root_file, ROOT_DIR . '/' . $a_root_file)) {
              $all_root_files_updated = false;
              $info[] = "Couldn't update $a_root_file, Consider updating them manually using src located at core/root";
              continue;
            }
          }
          if ($all_root_files_updated)
            deleteDirectory(CORE_DIR . '/root');

          unlink($file);

          // deleteDirectory(CORE_DIR . '-old');
          return new Response(['errors' => $info]);
        } else {
          deleteDirectory(CORE_DIR);
          rename(CORE_DIR . '-old', CORE_DIR);
          return new Response("Failed to open the downloaded zip file. Error code: $zip_status", 500);
        }
      case 403:
        return new Response("Contact lawaty@drolez-apps.cloud to obtain a license");
      default:
        return new Response($response, $httpCode);
    }
  }
}
