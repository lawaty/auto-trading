<?php

class Timing
{
  private Config $config;
  private string $which;

  public function __construct(string $which)
  {
    $this->config = Config::getInst();
    $this->which = $which;
  }

  public function waitTill(mixed $date): void
  {
    if (!($date instanceof Ndate))
      $date = new Ndate($date);

    $till_date = min(max((new Ndate)->secondsUntil($date), 1), 5 * 60 * 60);
    $tomorrow_1am = (new Ndate('01:00:00'))->addDays(1);
    $till_tomorrow = (new Ndate)->secondsUntil($tomorrow_1am);
    if ($till_tomorrow <= $till_date)
      $this->restartTomorrow();
    else {
      echo "Waiting $till_date secs ...\n";
      sleep($till_date);
    }
  }

  public function waitTillRun(array $run)
  {
    $market_start = new Ndate($this->config['globals']['open_time']);
    $run_start = $market_start->addMinutes($run['trade_after']);
    $this->waitTill($run_start);
  }

  public function getMarketTime(): Ndate
  {
    $market_time = new Ndate($this->config['globals']['open_time']);
    if ($this->config['globals']['is_holiday'])
      $market_time->addDays(1);
    
    return $market_time;
  }

  public function marketReady()
  {
    if ($this->config['globals']['is_holiday'])
      return false;

    $till_run = (new Ndate)->secondsUntil($this->getMarketTime());
    return $till_run <= 90;
  }

  public function restartTomorrow()
  {
    $tomorrow_1am = (new Ndate('01:00:00'))->addDays(1);
    $till_tomorrow = (new Ndate)->minutesUntil($tomorrow_1am) + 1;
    countdown("the next day at 1am", max(60 * $till_tomorrow, MIN_WAIT));

    $new_process = new Process("apply" . ucfirst($this->which) . "Strategies");
    $new_process->setLogger(APP_DIR . '/processes/logs/' . ucfirst($this->which) . '-' . (new Ndate)->format() . '.log');
    if ($new_process->run(Process::BACKGROUND))
      exit;
  }
}
