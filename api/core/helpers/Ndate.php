<?php

class Ndate extends DateTime
{
  const DATE_TIME = "Y-m-d H:i:s";
  const DATE = "Y-m-d";
  const TIME = "H:i:s";

  public function __construct(string $date = 'now')
  {
    parent::__construct($date);
  }

  public function minutesUntil(Ndate $date): int
  {
    return (int) $this->secondsUntil($date) / 60;
  }

  public function daysUntil(Ndate $date): int
  {
    return (int) $this->minutesUntil($date) / (24 * 60);
  }

  public function secondsUntil(Ndate $date): int
  {
    $diff = $this->diff($date);
    $seconds = $diff->days * 24 * 3600 + $diff->h * 3600 + $diff->i * 60 + $diff->s;

    // If the target date is before the current date, make the difference negative
    if ($this > $date)
      return -$seconds;
    else
      return $seconds;
  }


  public function toMinutes(): int
  {
    return $this->format('H') * 60 + $this->format('i');
  }

  public function addDays(int $days): Ndate
  {
    $interval = new DateInterval('P' . abs($days) . 'D');
    if ($days >= 0)
      $this->add($interval);
    else
      $this->sub($interval);

    return $this;
  }

  public function addSeconds(int $secs): Ndate
  {
    $interval = new DateInterval('PT' . abs($secs) . 'S');
    if ($secs >= 0)
      $this->add($interval);
    else
      $this->sub($interval);

    return $this;
  }

  public function format($format = self::DATE): string
  {
    return parent::format($format);
  }

  public function getWeekDates(): array
  {

    $dates = [];
    $temp = new Ndate($this->format());
    do {
      array_push($dates, $temp->format());
      $temp->addDays(-1);
    } while ($temp->format('D') != 'Fri');

    $temp = new Ndate($this->format());
    $temp->addDays(1);
    while ($temp->format('D') != 'Sat') {
      array_push($dates, $temp->format());
      $temp->addDays(1);
    }

    return $dates;
  }

  public function between(Ndate $date1, Ndate $date2)
  {
    $sign1 = $this->minutesUntil($date1);
    $sign2 = $this->minutesUntil($date2);
    $sign1 /= abs($sign1);
    $sign2 /= abs($sign2);

    return $sign1 * $sign2 < 0;
  }

  public function before(Ndate $date) {
    return $this->minutesUntil($date) > 0;
  }

  public function after(Ndate $date) {
    return $this->minutesUntil($date) < 0;
  }
}
