<?php

class UniquenessViolated extends Exception
{
  public function __construct(string $conflict, int $code = 409)
  {
    parent::__construct($conflict, $code);
  }
}