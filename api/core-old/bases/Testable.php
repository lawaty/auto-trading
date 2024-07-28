<?php

const ADMIN_ONLY_TEST_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6MSwidHlwZSI6MX0.5Rgrb0zmFl1UQXdX24nsVeOl78N4hjpKRjLwMHcXlkc';

interface Testable
{
  /**
   * A function used to initialize the needed entities, and define tests. Every test must have an input generator function and an array of assertions containing Assertion objects
   * 
   * KIS by defining naive scenarios each of which has a deterministic naive result that can be easily asserted (break scenarios into small naive ones instead of asserting complex scenarios)
   * 
   * @return array<Scenario> of inputs keyed by cases that every case has its own generated test input
   * 
   */
  public function arrange(): array;
}

class Scenario
{
  public string $name;
  private $inputGenerator;
  private array $assertions;

  public function __construct(string $name, $inputGenerator, array $assertions = [])
  {
    $this->name = $name;
    $this->inputGenerator = $inputGenerator;
    $this->assertions = $assertions;
  }

  public function generateInput(): array
  {
    return call_user_func($this->inputGenerator);
  }

  public function addAssertion(...$assertions)
  {
    $this->assertions = array_merge($this->assertions, $assertions);
  }

  public function assertAll(array $input, Response $output): array
  {
    $success = true;
    $results = [];
    foreach ($this->assertions as $assertion) {
      $results[$assertion->name] = $assertion->check($input, $output);
      if (!$results[$assertion->name])
        $success = false;
    }

    if (!$success)
      return $results;

    return [];
  }
}

class Assertion
{
  public string $name;
  private $checker;
  public function __construct(string $assertion_name, $checker)
  {
    $this->name = $assertion_name;
    $this->checker = $checker;
  }

  public function check(...$args): bool
  {
    return call_user_func($this->checker, ...$args);
  }
}
