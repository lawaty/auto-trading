<?php

class Ndate extends DateTime
{
  const DATE_TIME = "Y-m-d H:i";
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
    $now = (new Ndate());
    $diff = $this->diff($date);
    $seconds = $diff->days * 24 * 3600 + $diff->h * 3600 + $diff->i * 60 + $diff->s;
    if ($now > $date)
      return $seconds * -1;

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
}
