<?php

const REQ = true;
const req = true;
const OPT = false;
const opt = false;
const MANY = true;
const many = true;
const SINGLE = false;
const single = false;

class Validator
{
  private array $expect = [];
  private array $filtered = [];

  public function __construct(array $expect, bool $is_many = SINGLE)
  {
    foreach ($expect as $param => $criteria) {
      if(!isset($criteria[2]))
        $criteria[2] = false;

      if (!isset($criteria[0], $criteria[1])) {
        throw new InvalidArgumentException("Criteria must have at least two elements: required, validation, and optionally is_many but found: " . print_r($criteria, true));
      }

      var_dump($criteria);

      if (is_array($criteria[1])) { // Nesting Validators
        $this->expect[$param] = [
          $criteria[0],
          new Validator($criteria[1], $criteria[2]),
          $criteria[2]
        ];
      } elseif (!is_string($criteria[1]) && !is_callable($criteria[1])) {
        throw new InvalidArgumentException("Validation criteria must be regex string, callable function or associative array for nesting structures");
      } else {
        $this->expect[$param] = $criteria;
      }
    }
  }

  public function formatError(string $path_to_invalid, array $data) {
    $keys = explode(' -> ', $path_to_invalid);
    $value = $data;
    foreach ($keys as $key) {
      if (preg_match('/\[(.*?)\]$/', $key, $matches)) {
        $value = $value[explode('[', $key)][0][$matches[1]] ?? null;
      } else
        $value = $value[$key];
    }

    $value = json_encode($value);
    return "Invalid $path_to_invalid found:\n $value";
  }

  public function validate(array $input, string $path = ''): ?string
  {
    foreach ($this->expect as $param => $criteria) {
      [$required, $validation, $is_many] = $criteria;
      $currentPath = $path ? "$path -> $param" : $param;

      if ($required === REQ && empty($input[$param])) {
        return $currentPath;
      } elseif ($required === OPT && empty($input[$param])) {
        continue;
      }

      if ($is_many === SINGLE) {
        if ($invalid = $this->findInvalid($input[$param], $validation, $currentPath)) {
          return $invalid;
        }
      } else {
        if (!is_array($input[$param])) {
          return $currentPath;
        }

        if (array_keys($input[$param]) !== range(0, count($input[$param]) - 1))
          $input[$param] = [$input[$param]];

        foreach ($input[$param] as $index => $workpiece) {
          $temp_path = $currentPath . "[$index]";
          if ($invalid = $this->findInvalid($workpiece, $validation, $temp_path)) {
            return $invalid;
          }
        }
      }
    }

    $this->filtered = $this->filterInput($input);
    return null;
  }

  private function findInvalid($input, $criteria, string $path)
  {
    if (is_callable($criteria)) {
      try {
        return !call_user_func($criteria, $input) ? $path : null;
      } catch (Exception | Error $e) {
        echo (trace($e));
        return $path;
      }
    } elseif (is_string($criteria)) {
      return !preg_match($criteria, $input) ? $path : null;
    } elseif ($criteria instanceof Validator) {
      return $criteria->validate($input, $path);
    }
    return null;
  }

  private function filterInput(array $input): array
  {
    $result = [];
    foreach ($this->expect as $param => $criteria) {
      if (isset($input[$param])) {
        [$required, $validation, $is_many] = $criteria;
        if ($validation instanceof Validator) {
          if ($is_many === SINGLE) {
            $result[$param] = $validation->filterInput($input[$param]);
          } else {
            if (is_array($input[$param]) && array_keys($input[$param]) !== range(0, count($input[$param]) - 1)) {
              $input[$param] = [$input[$param]];
            }
            $result[$param] = array_map(fn ($item) => $validation->filterInput($item), $input[$param]);
          }
        } else {
          if ($is_many === SINGLE) {
            if (is_array($input[$param]))
              if (isset($input[$param][0]))
                $input[$param] = $input[$param][0];
              else if (empty($input[$param]))
                $input[$param] = '';

            $result[$param] = $input[$param];
          } else {
            if (is_array($input[$param]) && array_keys($input[$param]) !== range(0, count($input[$param]) - 1)) {
              $input[$param] = [$input[$param]];
            }
            $result[$param] = $input[$param];
          }
        }
      }
    }

    return $result;
  }

  public function getFiltered(): array
  {
    return $this->filtered;
  }

  public function printTree(int $indentation = 0): void
  {
    echo "<pre>";
    foreach ($this->expect as $param => $criteria) {
      [$required, $is_many, $validation] = $criteria;
      echo str_repeat("    ", $indentation) . "$param => ";

      if (is_string($validation)) {
        echo "[" . ($required ? 'REQ' : 'OPT') . ", " . ($is_many ? 'MANY' : 'SINGLE') . ", " . $validation . "]";
      } elseif ($validation instanceof Validator) {
        echo "[" . ($required ? 'REQ' : 'OPT') . ", " . ($is_many ? 'MANY' : 'SINGLE') . ", \n";
        $validation->printTree($indentation + 1);
        echo str_repeat("    ", $indentation) . "]";
      } elseif (is_callable($validation)) {
        echo "[" . ($required ? 'REQ' : 'OPT') . ", " . ($is_many ? 'MANY' : 'SINGLE') . ", function()]\n";
      }
      echo "\n";
    }

    if ($indentation == 0) {
      echo "\n";
    }

    echo "</pre>";
  }
}

// // Example usage of the Validator class
// $exampleExpect = [
//   'key1' => [REQ, SINGLE, '/^[a-zA-Z]+$/'], // Assuming Regex::NAME = '/^[a-zA-Z]+$/'
//   'key2' => [REQ, MANY, '/^[a-zA-Z]+$/'],
//   'key3' => [OPT, SINGLE, '/^[a-zA-Z]+$/'],
//   'key4' => [OPT, MANY, '/^[a-zA-Z]+$/'],
//   'key5' => [REQ, SINGLE, [
//     'key1' => [REQ, SINGLE, '/^[a-zA-Z]+$/'],
//     'key2' => [REQ, MANY, '/^[a-zA-Z]+$/'],
//     'key3' => [OPT, SINGLE, '/^[a-zA-Z]+$/'],
//     'key4' => [OPT, MANY, '/^[a-zA-Z]+$/']
//   ]],
//   'key6' => [REQ, MANY, [
//     'key1' => [REQ, SINGLE, '/^[a-zA-Z]+$/'],
//     'key2' => [REQ, MANY, '/^[a-zA-Z]+$/'],
//     'key3' => [OPT, SINGLE, '/^[a-zA-Z]+$/'],
//     'key4' => [OPT, MANY, '/^[a-zA-Z]+$/']
//   ]],
//   'key7' => [OPT, SINGLE, [
//     'key1' => [REQ, SINGLE, '/^[a-zA-Z]+$/'],
//     'key2' => [REQ, MANY, '/^[a-zA-Z]+$/'],
//     'key3' => [OPT, SINGLE, '/^[a-zA-Z]+$/'],
//     'key4' => [OPT, MANY, '/^[a-zA-Z]+$/']
//   ]],
//   'key8' => [OPT, MANY, [
//     'key1' => [REQ, SINGLE, '/^[a-zA-Z]+$/'],
//     'key2' => [REQ, MANY, '/^[a-zA-Z]+$/'],
//     'key3' => [OPT, SINGLE, '/^[a-zA-Z]+$/'],
//     'key4' => [OPT, MANY, '/^[a-zA-Z]+$/']
//   ]],
// ];

// $validator = new Validator($exampleExpect);
// $input = [
//   'key1' => 'John',
//   'key2' => ['Doe', 'Smith'],
//   'key3' => 'Alice',
//   'key4' => ['Bob', 'Carol'],
//   'key5' => [
//     'key1' => 'David',
//     'key2' => ['Eve', 'Frank'],
//     'key3' => 'Grace',
//     'key4' => ['Heidi', 'Ivan']
//   ],
//   'key6' => [
//     [
//       'key1' => 'Judy',
//       'key2' => ['Ken', 'Leo'],
//       'key3' => 'Mallory',
//       'key4' => 'lol'
//     ],
//     [
//       'key1' => 'Peggy',
//       'key2' => ['Quentin', 'Rob'],
//       'key3' => 'Sybil',
//       'key4' => ['Trent', 'Uma']
//     ]
//   ],
//   'key7' => [
//     'key1' => 'Victor',
//     'key2' => ['Walter', 'Xander'],
//     'key3' => 'Yvonne',
//     'key4' => ['Zack', 'Amber']
//   ],
//   'key8' => [
//     [
//       'key1' => 'Barry',
//       'key2' => ['Cindy', 'Derek'],
//       'key3' => 'Eli',
//       'key4' => ['Fay', 'Gina']
//     ],
//     [
//       'key1' => 'Hank',
//       'key2' => ['Ivy', 'Jack'],
//       'key3' => 'Karen',
//       'key4' => ['Liam', 'Mona']
//     ]
//   ],
// ];

// $result = $validator->validate($input);
// echo $result ?? 'All inputs are valid.';
