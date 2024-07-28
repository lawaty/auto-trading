<?php

/**
 * The Logger class handles logging and error reporting.
 */
class ArteLogger
{
  /**
   * @var string The log file path.
   */
  private static string $log_file;
  private array $logs;
  private static ArteLogger $inst;

  /**
   * @var Ndate The current date.
   */
  private Ndate $date;

  /**
   * Logger constructor.
   *
   * Initializes the log file and file handle.
   *
   * @throws Fail If there is a server permission error.
   */
  public function __construct()
  {
    if (!file_exists(LOG_DIR))
      mkdir(LOG_DIR);
    self::$log_file = LOG_DIR . "/" . (new Ndate())->format() . ".log";

    $this->date = new Ndate();

    if (file_exists(self::$log_file))
      $this->logs = json_decode(file_get_contents(self::$log_file), true) ?? [];
    else
      $this->logs = [];
  }

  public static function getLogger(): ArteLogger
  {
    if (!isset(self::$inst))
      self::$inst = new self();

    return self::$inst;
  }

  /**
   * Logs the endpoint and response details.
   *
   * @param string $controller
   * @param Endpoint|null $endpoint The endpoint object.
   * @param Response $response The response objectx.
   * @throws Exception If logging fails.
   */
  public function log(Route $route, Response $response): void
  {
    $fhand = fopen(self::$log_file, "w");

    if (!$fhand)
      throw new Exception("Server Permission Error");

    $response_body = $response->getCode() > 204 ? json_encode($response->getBody()) : "success";

    $duration = isset($route->endpoint) ? $route->endpoint->getDuration() : 0;

    $this->logs[] = [
      'route' => $route->uri,
      'payload' => json_encode($_REQUEST, JSON_UNESCAPED_UNICODE),
      'response' => $response->getCode() . " $response_body",
      'date' => $this->date->format(Ndate::DATE_TIME),
      'endpoint_duration' => $duration,
      'client_ip' => $_SERVER['REMOTE_ADDR'],
      'hidden_buffer' => substr($response->hidden_buffer, 0, 1000)
    ];

    if (!fwrite(
      $fhand,
      json_encode($this->logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) && DEBUG)
      throw new Exception('Logging Failed');

    fclose($fhand);
  }

  /**
   * Reports an error to the log file.
   *
   * @param string $error The error message.
   * @throws Exception If logging fails.
   */
  public function reportError(string $error)
  {
    $err_log_file = LOG_DIR . "/" . (new Ndate())->format() . ".err";
    if (file_exists($err_log_file))
      $errs = json_decode(file_get_contents($err_log_file), true) ?? [];
    else
      $errs = [];

    $fhand = fopen($err_log_file, "w");

    $errs[] = $error;

    if (!fwrite($fhand, json_encode($errs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)))
      throw new Exception('Logging Failed');

    fclose($fhand);
  }

  public function getLogs(): string
  {
    $array = array_reverse($this->logs);
    return str_replace('\\"', '"', json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  }
}
