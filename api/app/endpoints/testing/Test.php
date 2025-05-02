<?php

class Test extends Endpoint
{
  public function __construct()
  {
    $this->init([
      "client-mappings" => [false, Regex::ANY], // Expecting JSON string
      "control-mappings" => [false, Regex::ANY]  // Expecting JSON string
    ], $_POST);
  }

  public function handle(): Response
  {
    try {
      // Decode sample_1 JSON
      $sample_1 = json_decode($this->request["sample_1"], true);
      if (!is_array($sample_1)) {
        throw new Exception("Invalid sample_1 format.");
      }

      // Generate validation schema dynamically
      $expect = $this->generateValidationSchema($sample_1);

      // Validate sample_2 using the generated schema
      $validator = new Validator($expect);
      $validator->printTree();
      prettyPrint($validator->getAllInvalid(json_decode($this->request["sample_2"], true)));
      return new Response(["status" => "success"]);
    } catch (Exception $e) {
      return new Response(["error" => $e->getMessage()], 400);
    }
  }

  private function generateValidationSchema($data)
  {
    $schema = [];

    foreach ($data as $key => $value) {
      if (is_array($value)) {
        if ($this->isNumericallyIndexedArray($value)) {
          // If it's a numerically indexed array, process only the first element
          $schema[$key] = [REQ, $this->generateValidationSchema($value[0]), MANY];
        } else {
          // If it's an associative array, recurse normally
          $schema[$key] = [REQ, $this->generateValidationSchema($value)];
        }
      } elseif (is_string($value)) {
        // Check if the string is a JSON array or object
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
          $schema[$key] = [
            REQ,
            function ($val) {
              return is_array(json_decode($val, true));
            }
          ];
        } else {
          $schema[$key] = [REQ, "/^.+$/"]; // Generic string regex
        }
      } elseif (is_numeric($value)) {
        $schema[$key] = [REQ, "/^\d+$/"]; // Number validation
      } elseif (is_bool($value)) {
        $schema[$key] = [
          REQ,
          function ($val) {
            return in_array($val, [true, false, "true", "false", 1, 0, "1", "0"], true);
          }
        ];
      }
    }

    return $schema;
  }

  private function isNumericallyIndexedArray(array $array): bool
  {
    return array_keys($array) === range(0, count($array) - 1);
  }

}
