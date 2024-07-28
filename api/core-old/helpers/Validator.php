<?php

class Validator
{
	private array $expect = [];
	private array $filtered = [];
	private bool $required = true;

	public function __construct(array $expect, string $class = null)
	{
		if (isset($expect[0]) && isset($expect[1])) {
			$this->required = $expect[0];
			$expect = $expect[1];
		}

		foreach ($expect as $param => $criteria) {
			if (is_string($criteria[1]))
				$this->expect[$param] = new ValidationUnit($criteria, $class);
			else
				$this->expect[$param] = new Validator($criteria, $class);
		}
	}

	private function validateAssoc(array $input)
	{
		/**
		 * validates all expectation parameters for a given associtative array
		 * returns string if invalid parameter found
		 * returns array if collection is filtered
		 */
		$result = [];
		foreach ($this->expect as $param => $validation) {
			// param is required and not found
			if ($validation->isRequired() && !isset($input[$param]))
				return "parameter '$param' is not found";

			// param is not required and not found
			else if (!$validation->isRequired() && !isset($input[$param]))
				continue;

			//param is found
			if ($validation instanceof ValidationUnit) {
				if (!$validation->validItem($input, $param))
					return $param;

				$result[$param] = $input[$param];
			} else {
				if ($invalid = $validation->validate($input[$param]))
					return "$invalid in '$param'";

				$result[$param] = $validation->getFiltered();
			}
		}

		return $result;
	}

	public function validate(array $input): ?string
	{
		/**
		 * Validates all expectation parameters for all collections
		 * Checks if input is a list of collections or just an associtative list
		 */

		if (isAssoc($input)) {
			// associtative array validation
			$result = $this->validateAssoc($input);
			if (is_string($result))
				return $result;

			$this->filtered = $result;
		} else {
			// List validation collection after another
			foreach ($input as $collection) {
				$result = $this->validateAssoc($collection);
				if (is_string($result))
					return $result;

				array_push($this->filtered, $result);
			}
		}

		return null;
	}

	public function getFiltered(): array
	{
		return $this->filtered;
	}

	public function isRequired(): bool
	{
		return $this->required;
	}

	public function formatValidationTree(int $indentation = 0): string
	{
		$result = "";
		foreach ($this->expect as $param => $validation) {
			$result .= "\n";
			for ($i = 0; $i < $indentation; $i++)
				$result .= "    ";
			$result .= "$param => ";

			if ($validation instanceof ValidationUnit)
				$result .= "[" . ($validation->isRequired() ? 'true' : 'false') . ", " . $validation->getValidation() . "]";
			else {
				$result .= "[" . ($validation->isRequired() ? 'true' : 'false') . "," . $validation->formatValidationTree($indentation + 1) . "\n";
				for ($i = 0; $i < $indentation; $i++)
					$result .= "    ";
				$result .= "]";
			}
		}

		if ($indentation == 0)
			$result .= "\n\n";
		return $result;
	}
}
