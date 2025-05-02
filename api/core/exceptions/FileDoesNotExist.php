<?php

class FileDoesNotExist extends Exception
{
  public function __construct($filename)
  {
    parent::__construct($filename, 500);
  }
}