<?php

/**
 * Regex Class
 *
 * Provides predefined regular expressions for common validation tasks.
 */
class Regex
{
  /**
   * Identifiers
   */
  const ID = '/^[1-9][0-9]{0,10}$/u';
  const INT = '/^[0-9]{1,10}$/u';
  const SINT = '/^-?[0-9]{1,10}$/u';
  const ZERO_ONE = '/^[0-1]$/u';

  /**
   * Dates
   */
  const MONTH = '/^([1-9]|1[0-2])$/u';
  const DATE = '/^(\d{4})-(\d{2})-(\d{2})$/u';
  const TIME = '/^[0-9]{2}:[0-9]{2}$/u';
  const DAY = '/^Sun|Mon|Tue|Wed|Thu|Fri|Sat$/u';
  const TIME_SEC = '/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/u';
  const DATE_TIME = '/^(\d{4})-(\d{2})-(\d{2}) [0-9]{2}:[0-9]{2}$/u';
  const DATE_TIME_SEC = '/^(\d{4})-(\d{2})-(\d{2}) [0-9]{2}:[0-9]{2}:[0-9]{2}$/u';
  const DAY_TIME = '/^(Sun|Mon|Tue|Wed|Thu|Fri|Sat) [0-9]{2}:[0-9]{2}$/u';

  /**
   * Other Patterns
   */
  const JWT_BEARER = '/^Bearer\s+[\w-]*\.[\w-]*\.[\w-]*$/';
  const LOGIN = '/^[\p{Arabic}\w\-. ]{6,40}$/u';
  const EMAIL = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/';
  const NAME = '/^[\p{Arabic}\w\-. ]{1,60}$/u';
  const PHONE = '/^[0-9\+]{5,17}$/u';
  const GRADE = '/^([1-9]|1[0-2])$/u';
  const HEX = '/^#([a-f0-9]{3}){1,2}\b$/i';

  /**
   * General
   */
  const GENERIC = '[\p{Arabic}\w\-\\\)\(.,:\s+]';
  const ANY = '/^(.|\s)+$/u';

  const AR_CHARS = 'أبتثجحخدذرزسشصضطظعغفقكلمنهوي';
  const NUMERALS = '0123456789';
  const EN_CHARS = 'abcdefghijklmnopqrstuvwxyz';
  const SPECIAL_CHARS = '\-\)\(.,:\s+';
  private static array $tables_IDs = [];

  /**
   * Get a generic regular expression pattern with a specified length range.
   *
   * @param int $min Minimum length
   * @param int $max Maximum length
   * @return string The regular expression pattern
   */
  public static function generic($min, $max): string
  {
    return '/^' . self::GENERIC . '{' . $min . ',' . $max . '}$/u';
  }

  /**
   * Get a regular expression pattern for separated values using a specified delimiter.
   *
   * @param string $delimiter The delimiter
   * @return string The regular expression pattern
   * @note that it allows empty strings to pass
   */
  public static function separated(string $delimiter, string $pattern = "[\p{Arabic}\w\-\\\)\(.,:\s+]+"): string
  {
    $escapedDelimiter = preg_quote($delimiter, '/');
    return "/^($pattern|$escapedDelimiter)*($pattern)?$|^$/u";
  }


  public static function generate(string $regex): string
  {
    switch ($regex) {
      case 'LOGIN':
        return static::generateLogin();
      case 'EMAIL':
        return  static::generateEmail();
      case 'NAME':
        return static::generateName();
      case 'PHONE':
        return static::generatePhone();
      case 'GRADE':
        return static::generateGrade();
      case 'GENERIC':
        return static::generateGeneric();
    }
  }

  public static function generateLogin(int $len = 20): string
  {
    $space = strtoupper(static::EN_CHARS) . static::EN_CHARS . static::NUMERALS . '-. ';
    $last_i = strlen($space) - 1;

    $result = '';
    for ($i = 0; $i < $len; $i++)
      $result .= $space[random_int(0, $last_i)];

    return $result;
  }

  public static function generateEmail()
  {
    $portion1 = str_replace([' ', '.'], '', self::generateLogin(10));
    $portion2 = str_replace([' ', '.'], '', self::generateLogin(10));
    $portion3 = str_replace([' ', '.', '-', '_'], '', self::generateLogin(3));

    return "$portion1@$portion2.$portion3";
  }

  public static function generateName(int $len = 20): string
  {
    $space = strtoupper(static::EN_CHARS) . static::EN_CHARS . '-. ';
    $last_i = strlen($space) - 1;

    $result = '';
    for ($i = 0; $i < $len; $i++)
      $result .= $space[random_int(0, $last_i)];

    return $result;
  }

  public static function generatePhone(int $len = 10): string
  {
    $space = static::NUMERALS;
    $last_i = strlen($space) - 1;

    $result = '';
    for ($i = 0; $i < $len; $i++)
      $result .= $space[random_int(0, $last_i)];

    return $result;
  }

  public static function generateGrade(): string
  {
    return random_int(1, 12);
  }

  public static function generateGeneric(int $len = 20): string
  {
    $space = strtoupper(static::EN_CHARS) . static::EN_CHARS . static::NUMERALS . static::SPECIAL_CHARS;
    $last_i = strlen($space) - 1;

    $result = '';
    for ($i = 0; $i < $len; $i++)
      $result .= $space[random_int(0, $last_i)];

    return $result;
  }

  public static function generateDatetime(): string
  {
    $random_date = new Ndate();
    $random_date->addDays(random_int(-30, 30));
    $random_date->addSeconds(random_int(-86400, 86400));
    return $random_date->format(Ndate::DATE_TIME);
  }

  public static function generateDate(): string
  {
    $random_date = new Ndate();
    $random_date->addDays(random_int(-30, 30));
    $random_date->addSeconds(random_int(-86400, 86400));
    return $random_date->format();
  }

  public static function generateID(string $table, $existing = true): string
  {
    if ($existing) { // Returning an existing ID
      $record = DB::getDB()->select('id', $table, ['id > 0', 'ORDER BY RAND() LIMIT 1']);
      if(!empty($record))
        return $record[0]['id'];
      else
        throw new Exception("Table $table is empty, so the generator failed to find an existing id");
    } else { // returning a non-existing ID
      $temp_id = -1;
      while ($temp_id == -1 || !empty($record = DB::getDB()->select('id', $table, ['id' => $temp_id]))) // try another one
        $temp_id = random_int(0, PHP_INT_MAX);

      return $temp_id;
    }
  }
}
