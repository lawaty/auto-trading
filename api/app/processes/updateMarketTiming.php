<?php

require_once __DIR__ . "../../../autoload.php";

function restart()
{
  $new_process = new Process("updateMarketTiming", LOG_DIR . '/market-timing-' . (new Ndate)->format(Ndate::DATE) . '.log');
  if ($new_process->run(Process::BACKGROUND)) {
    exit;
  }
}

function restartTomorrow()
{
  echo "Today is a holiday. The market is closed.\n";
  $restart_at = (new Ndate('01:00:00'))->addDays(1);

  // Wait until 1 am tomorrow to restart
  $wait_time = (new Ndate())->minutesUntil($restart_at);
  echo "Waiting until 1 am tomorrow to restart (in $wait_time minutes).\n";
  sleep($wait_time * 60 + 1); // Convert to seconds and wait

  restart(); // Restart the process after waiting
}

while (true) {
  try {
    echo "Fetching...\n";
    $html = file_get_contents("https://api.crawlbase.com/?token=LDHkofAU5ilNXO0TaXr-DQ&url=https://www.forexchurch.com/stock-market-holidays/new-york-stock-exchange");

    if ($html === FALSE) {
      echo "Failed to fetch the HTML content.\n";
      continue;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $query = "//table[@id='holStatusTable']/tbody/tr[1]";
    $nodes = $xpath->query($query);

    // Holiday Check
    if (preg_match('/isholiday\s*=\s*(true|false)/i', $html, $matches)) {
      $isholiday = ($matches[1] === 'true');
    } else {
      $holidays = extractHolidays($dom);
      $isholiday = in_array((new Ndate())->format(), $holidays);
    }

    if ($isholiday){
      restartTomorrow();

      // Negative Section
      echo "Negative Section Reached !!\n";
      continue;
    }

    echo "Today is not a holiday. Checking Open Time ...\n";

    if ($nodes->length > 0) {
      $firstRow = $nodes->item(0);
      $data = $firstRow->childNodes;
    } else {
      echo "Failed to find the table or its first row.\n";
      sleep(60 * 2);
      continue;
    }

    $status = trim($data->item(1)->textContent);
    $open_time = new Ndate(trim($data->item(3)->textContent));
    $close_time = new Ndate(trim($data->item(5)->textContent));
    $now = new Ndate;

    if ($now->before($open_time))
      $event_at = $open_time;
    else if ($now->before($close_time))
      $event_at = $close_time;
    else {
      $event_at = $open_time;
      $event_at->addDays(1);
    }

    $all_params = json_decode(file_get_contents(JSONS_DIR . '/params.json'), true);

    if ((new Ndate($all_params['globals']['until']))->format(Ndate::DATE_TIME) == $open_time->format(Ndate::DATE_TIME) && (new Ndate)->after($open_time))
      $status = 'Open';

    $all_params['globals']['status'] = $status;
    $all_params['globals']['open_time'] = $open_time->format(Ndate::DATE_TIME);
    $all_params['globals']['until'] = $event_at->format(Ndate::DATE_TIME);
    echo "Status: $status\nOpen At: {$open_time->format(Ndate::DATE_TIME)}\nNext Event: {$event_at->format(Ndate::DATE_TIME)}\n";
    file_put_contents(JSONS_DIR . '/params.json', json_encode($all_params));

    $restart_at = (new Ndate('01:00:00'))->addDays(1);
    $till_event = $now->minutesUntil($event_at);
    $max_wait = 60 * 8;

    echo "Status: $status\tNext bell rings in $till_event mins \n";
    echo "Market Timing is Updated — at " . $now->format(Ndate::DATE_TIME) . "\n";

    $wait_time = min($max_wait, ($till_event));

    echo "Waiting $wait_time mins till next update.\n\n";
    sleep($wait_time * 60 + 1); // Convert to seconds for sleep

    if ((new Ndate())->after($restart_at)) {
      restart();
    }
  } catch (Exception | Error $e) {
    echo (new Ndate)->format(Ndate::DATE_TIME) . ": " . trace($e) . "\n";
  }
}

function extractHolidays(DomDocument $dom): array
{
  $xpath = new DOMXPath($dom);
  $query = "//table[contains(@class, 'panel-table-no-side-borders')]//tbody/tr";
  $holidayRows = $xpath->query($query);

  $holidays = [];
  /**
   * @var DOMNode
   */
  foreach ($holidayRows as $row) {
    $cells = $row->getElementsByTagName('td');

    if ($cells->length == 2) {
      $holidayDate = trim($cells->item(1)->nodeValue);
      $holidays[] = (new Ndate($holidayDate))->format();
    }
  }

  return $holidays;
}
