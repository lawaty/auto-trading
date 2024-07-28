<?php

class ValidationUnit
{

  private bool $required;
  private string $validation;
  private ?string $class;

  public function __construct(array $criteria, string $class)
  {
    $this->required = $criteria[0];
    $this->validation = $criteria[1];
    $this->class = $class;
  }

  public function validItem(array $input, string $param): bool
  {
    if ($this->class && method_exists($this->class, $this->validation)) {
			// Custom Validation
			if (!$this->class::{$this->validation}($input, $param))
				return false;
		} else {
			// Regex Validation
			if (
				// required item validation
				$this->required && !(isset($input[$param]) && preg_match($this->validation, $input[$param]))
				||
				// optional item validation
				!$this->required && !empty($input[$param]) && !preg_match($this->validation, $input[$param])
			)
				return false;
      
		}
		return true;
  }

  public function isRequired(): bool
  {
    return $this->required;
  }

  public function getValidation(): string
  {
    return $this->validation;
  }
}
