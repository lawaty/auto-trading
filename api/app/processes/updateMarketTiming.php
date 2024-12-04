<?php

require_once __DIR__ . "../../../autoload.php";

interface MarketTimingScrapper
{
  public function isHoliday(): bool;
  public function getOpenTime(): Ndate;
  public function getCloseTime(): Ndate;
  public function getStatus(): string;
}

class ForexScrapper implements MarketTimingScrapper
{
  private string $status;
  private Ndate $open_time;
  private Ndate $close_time;
  private bool $is_holiday;

  public function __construct()
  {
    $this->scrape();
  }

  private function scrape(): void
  {
    $html = file_get_contents("https://www.forexchurch.com/stock-market-holidays/new-york-stock-exchange");

    if ($html === FALSE)
      throw new ScrapeFailed('Received nothing from the destination');

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $query = "//table[@id='holStatusTable']/tbody/tr[1]";
    $nodes = $xpath->query($query);

    // Holiday Check
    preg_match('/isholiday\s*=\s*(true|false)/i', $html, $matches1);
    preg_match('/weekend\s*=\s*(true|false)/i', $html, $matches2);
    if ($matches1[1] === 'true' || $matches2[1] === 'true') {
      $this->is_holiday = true;
    } else {
      $holidays = extractHolidays($dom);
      $this->is_holiday = in_array((new Ndate())->format(), $holidays);
    }

    // Updating Configurations
    if ($nodes->length > 0) {
      $firstRow = $nodes->item(0);
      $data = $firstRow->childNodes;
    } else
      throw new ScrapeFailed("Failed to find the table or its first row.\n");

    $this->open_time = new Ndate(trim($data->item(3)->textContent));
    $this->close_time = new Ndate(trim($data->item(5)->textContent));
    $config = Config::getInst();

    $this->status = trim($data->item(1)->textContent);
    if ((new Ndate($config['globals']['until']))->format(Ndate::DATE_TIME) == $this->open_time->format(Ndate::DATE_TIME) && (new Ndate)->after($this->open_time))
      $this->status = 'Open';
  }

  public function isHoliday(): bool
  {
    return $this->is_holiday;
  }

  public function getOpenTime(): Ndate
  {
    return $this->open_time;
  }

  public function getCloseTime(): Ndate
  {
    return $this->close_time;
  }

  public function getStatus(): string
  {
    return $this->status;
  }
}

class ScrapeFailed extends Exception {}

$process_date = new Ndate('01:00:00');

// Use the Timing class for waiting logic
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

  // Use the Timing class to wait until 1 am tomorrow
  $timing = new Timing('Market');
  $timing->waitTill((new Ndate('01:00:00'))->addDays(1));

  restart(); // Restart the process after waiting
}

while (true) {
  try {
    echo "Fetching...\n";

    try {
      $scrapper = new ForexScrapper();
    } catch (ScrapeFailed $e) {
      echo "Scrape Failed: " . $e->getMessage() . "\n";
      sleep(2 * 60);
      continue;
    }

    $status = $scrapper->getStatus();
    $open_time = $scrapper->getOpenTime();
    $close_time = $scrapper->getCloseTime();
    $now = new Ndate;

    if ($now->before($open_time))
      $event_at = $open_time;
    else if ($now->before($close_time))
      $event_at = $close_time;
    else {
      $event_at = $open_time;
      $event_at->addDays(1);
      $close_time->addDays(1);
    }

    $all_params = Config::getInst()->toArray();
    $all_params['globals']['is_holiday'] = $scrapper->isHoliday();
    $all_params['globals']['status'] = $status;
    $all_params['globals']['open_time'] = $open_time->format(Ndate::DATE_TIME);
    $all_params['globals']['close_time'] = $close_time->format(Ndate::DATE_TIME);
    $all_params['globals']['until'] = $event_at->format(Ndate::DATE_TIME);
    echo "Status: $status\nOpen At: {$open_time->format(Ndate::DATE_TIME)}\nNext Event: {$event_at->format(Ndate::DATE_TIME)}\n";
    file_put_contents(JSONS_DIR . '/params.json', json_encode($all_params));

    // Use Timing class to wait until the next event
    $timing = new Timing('Market');
    $timing->waitTill($event_at);

    // Check for holiday and restart logic
    if ($scrapper->isHoliday()) {
      restartTomorrow();
      echo "Negative Section Reached !!\n";
      continue;
    }

    echo "Today is not a holiday. Checking Open Time ...\n";

    echo "Market Timing is Updated — at " . $now->format(Ndate::DATE_TIME) . "\n";

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
   * @var \DOMElement
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
