<?php

/**
 * The Response class handles HTTP responses.
 */
class Response
{
  /**
   * @var int The HTTP response code.
   */
  private int $code;

  /**
   * @var mixed The response body.
   */
  private mixed $body;

  /**
   * @var mixed Any extra headers to be added to response
   */
  private array $headers;

  /**
   * @var string The hidden buffer for debug mode.
   */
  public string $hidden_buffer;

  /**
   * Response constructor.
   *
   * @param mixed $body The response body (optional).
   * @param int $code The HTTP response code (default: 200).
   */
  public function __construct($body = '', int $code = 200, array $extra_headers = [])
  {
    if ($body instanceof Response){
      $this->body = $body->getBody();
      $this->code = $body->getCode();
      $this->headers = $body->getHeaders();
      return ;
    }

    if (is_iterable($body) || $body instanceof JsonSerializable)
      $this->body = json_encode($body, JSON_UNESCAPED_UNICODE);
    else
      $this->body = $body;

    $this->code = $code;

    $this->headers = $extra_headers;
  }

  /**
   * Retrieves the HTTP response code.
   *
   * @return int The HTTP response code.
   */
  public function getCode(): int
  {
    return $this->code;
  }

  /**
   * Retrieves the response body.
   *
   * @return mixed The response body.
   */
  public function getBody()
  {
    return $this->body;
  }

  /**
   * Retrieves the response headers.
   *
   * @return array The response headers.
   */
  public function getHeaders()
  {
    return $this->headers;
  }

  /**
   * Outputs the response.
   */
  public function echo(): void
  {
    // Preparing Variables and Settings
    header('Connection: close');
    ignore_user_abort(true);

    $this->hidden_buffer = ob_get_clean();

    // Headers Section
    if ($this->body instanceof ResponseFile) {
      header("Content-Type: " . $this->body->mime, true);
    } else {
      if (DEBUG) {
        header('Content-Length: ' . strlen($this->body) + strlen($this->hidden_buffer));
      } else
        header('Content-Length: ' . strlen($this->body));
    }
    http_response_code($this->code);
    // Extra Headers
    foreach ($this->headers as $header)
      header($header);

    // Output Section
    if ($this->body instanceof ResponseFile)
      readfile($this->body->path);
    else if (DEBUG) {
      echo $this->hidden_buffer;
      echo $this->body;
    } else
      echo $this->body;

    // Closing Connection
    while (ob_get_level() > 0)
      ob_end_flush();

    flush();
  }
}

class ResponseFile
{
  public string $path;
  public string $mime;

  public function __construct(string $path)
  {
    if (!file_exists($path))
      throw new FileDoesNotExist($path);

    $this->path = $path;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $this->mime = finfo_file($finfo, $this->path);
    finfo_close($finfo);
  }
}
