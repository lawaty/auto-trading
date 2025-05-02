<?php

class PermissionDenied extends Exception
{
  public function __construct(string $path)
  {
    parent::__construct("Permission Denied: $path", 500);
  }
}