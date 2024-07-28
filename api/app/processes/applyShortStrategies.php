<?php

require_once __DIR__ . "../../../autoload.php";

$config = new Config;
$run_before = false;
const INIT_TIME = 1;

while (true) {
  echo "Waiting ". INIT_TIME . " minutes before fetching market status\n";
  sleep(INIT_TIME  * 60);

  try {
    $config->refresh();
    $status = $config['globals']['status'];
    $bell = new Ndate($config['globals']['until']);
    $till_bell = (new Ndate)->minutesUntil($bell);

    if ($till_bell < 0)
      $status = $config['globals']['status'] == 'Open' ? 'Closed' : 'Open';

    if (!$run_before && $status == 'Open') {
      try {
        StockLogger::emptyStock('Short');

        foreach ($config['short'] as $i => $run) {
          $log_dir = __DIR__ . "/logs/short/" . (new Ndate)->format();
          if(!is_dir($log_dir))
            mkdir($log_dir);

          $run['wait'] -= INIT_TIME;

          $process = new Process("ShortStrategy", "$log_dir/run-$i.log");
          $process->passArgs($run);
          $process->run(Process::BACKGROUND);
        }

        $run_before = true;
      } catch (Exception |  Error $e) {
        echo "Encountered some weird behavior!\n {trace($e)}\n\n Quitting Market... \n\n";
        var_dump($e);
      }

      $till_close = $till_bell;
      echo "Finished trading. Waiting till the market closes after $till_close mins";
      sleep(max($till_close, 1) * 60); // wait till closes again
    } else {
      $till_open = $till_bell;
      $tomorrow_1am = (new Ndate('01:00:00'))->addDays(1);
      $till_tomorrow = (new Ndate)->minutesUntil($tomorrow_1am);

      if ($till_tomorrow < $till_open)
        restartTomorrow();
      else {
        echo "Waiting till the next market opening: $till_open mins\n";
        sleep($till_open * 60);
      }
    }
  } catch (Exception | Error $e) {
    file_put_contents(__DIR__ . '/logs/Short-' . (new Ndate)->format() . '.err', trace($e) . "\n", FILE_APPEND);
  }
}

function restartTomorrow()
{
  $tomorrow_1am = (new Ndate('01:00:00'))->addDays(1);
  $till_tomorrow = (new Ndate)->minutesUntil($tomorrow_1am);
  echo "Waiting till the next day at 1am to restart the process: $till_tomorrow mins\n";
  sleep(60 * max($till_tomorrow, 1));

  $new_process = new Process("applyShortStrategies");
  $new_process->setLogger(APP_DIR . '/processes/logs/Short-' . (new Ndate)->format() . '.log');
  if ($new_process->run(Process::BACKGROUND)) {
    $json = json_decode(file_get_contents(JSONS_DIR . "/Short.json"), true);
    $json['pid'] = $new_process->getPID();
    file_put_contents(JSONS_DIR . "/Short.json", json_encode($json));
    exit;
  }
}
