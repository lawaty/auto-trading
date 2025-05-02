<?php

class ArteCurl
{
  const MAX_BUFFER_SIZE = 1000;
  private string $url;
  private ?CurlHandle $curl;
  private array $headers = [];
  private array $response_headers = [];
  private string $error = '';
  private $log_file;

  private ?string $buffer = null;
  private mixed $data = null;
  private bool $streaming = false;
  private mixed $stream_callback;

  public function __construct(string $endpoint)
  {
    $this->url = $endpoint;
    $this->curl = curl_init();

    $path = LOG_DIR . '/outbound/' . (new Ndate())->format() . ".log";
    $this->log_file = fopen($path, "a");
    if (!$this->log_file)
      throw new PermissionDenied($path);
  }

  public function setHeaders(array $headers): void
  {
    foreach ($headers as $key => $value)
      $this->headers[] = "$key: $value";
  }

  public function send(string $request_type, array $data = [], $logging = false): Response
  {
    curl_setopt($this->curl, CURLOPT_URL, $this->url);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, [$this, 'headerCallback']);

    $this->prepareRequest($request_type, $data);

    if (!empty($this->headers))
      curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);

    $response = curl_exec($this->curl);
    $http_status_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

    if (curl_errno($this->curl)) {
      $this->error = curl_error($this->curl);
      throw new Exception('cURL error: ' . curl_error($this->curl));
    }

    $this->closeCurl();

    $response = new Response($response, $http_status_code, $this->response_headers);

    if ($logging)
      $this->log("$request_type {$this->url}", $data, $response);

    return $response;
  }

  private function headerCallback($ch, $header)
  {
    $length = strlen($header);
    $headerParts = explode(':', $header, 2);
    if (count($headerParts) == 2) {
      $this->response_headers[trim($headerParts[0])] = trim($headerParts[1]);
    }
    return $length;
  }

  private function prepareRequest($request_type, $data)
  {
    $request_type = strtolower($request_type);
    switch ($request_type) {
      case 'post':
        curl_setopt($this->curl, CURLOPT_POST, true);
        $this->setPostFields($data);
        break;
      case 'get':
        $this->setGetFields($data);
        break;
      case 'put':
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "PUT");
        $this->setPostFields($data);
        break;
      case 'delete':
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        $this->setPostFields($data);
        break;
    }
  }

  private function setPostFields($data)
  {
    if (is_array($data)) {
      $post_fields = json_encode($data);
      $this->setHeaders(['Content-Type' => 'application/json']);
    } else {
      $post_fields = $data;
    }
    curl_setopt($this->curl, CURLOPT_POSTFIELDS, $post_fields);
  }

  private function setGetFields($data)
  {
    if (!empty($data)) {
      $full_url = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL) . '?' . http_build_query($data);
      curl_setopt($this->curl, CURLOPT_URL, $full_url);
    }
  }

  private function closeCurl()
  {
    if ($this->curl) {
      curl_close($this->curl);
      $this->curl = null;
    }
  }

  public function getError(): string
  {
    return $this->error;
  }

  private function log(string $request, array $payload, Response $response): void
  {
    fwrite($this->log_file, (new Ndate)->format(Ndate::DATE_TIME) . "\n$request \Headers: " . json_encode($this->headers, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES) . "\n\nBody: " . json_encode($payload, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES) . "\nResponse Code: " . $response->getCode() . "\nResponse Body: \n" . $response->getBody() . "\n\n");
  }

  public function __destruct()
  {
    fclose($this->log_file);
  }

  public function setStreamCallback(callable $callback): void
  {
    $this->stream_callback = $callback;
  }

  public function initStream()
  {
    curl_reset($this->curl);
    curl_setopt($this->curl, CURLOPT_URL, $this->url);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($this->curl, CURLOPT_BUFFERSIZE, 1024);
    curl_setopt($this->curl, CURLOPT_TIMEOUT, 0); // Remove timeout for persistent connection

    if (!is_callable($this->stream_callback)) {
      throw new InvalidArgumentException("Stream callback is not set or is not callable.");
    }

    $callback = $this->stream_callback;

    curl_setopt($this->curl, CURLOPT_WRITEFUNCTION, function ($ch, $response) use ($callback) {
      // Append the response chunk to the buffer
      $this->buffer .= $response;

      $openBraces = 0;
      $jsonStart = false;
      $jsonPart = '';

      // Loop through the buffer to detect and parse multiple JSON objects
      for ($i = 0, $len = strlen($this->buffer); $i < $len; $i++) {
        $char = $this->buffer[$i];

        if ($char === '{') {
          $openBraces++;
          if (!$jsonStart) {
            $jsonStart = true; // Mark the start of a JSON object
          }
        }

        if ($jsonStart) {
          $jsonPart .= $char; // Append character to current JSON part
        }

        if ($char === '}') {
          $openBraces--;

          if ($openBraces === 0 && $jsonStart) {
            // We have a complete JSON object
            $decodedData = json_decode($jsonPart, true);

            if (json_last_error() === JSON_ERROR_NONE) {
              // Call the callback with the parsed data
              if (!is_array($decodedData))
                $decodedData = [];

              if (!$this->data = call_user_func($callback, $decodedData)) {
                $this->streaming = false;
                return false;
              }
            } else {
              echo "JSON decoding error: " . json_last_error_msg() . "\n";
            }

            // Reset variables for the next JSON object
            $jsonStart = false;
            $jsonPart = '';

            // Remove processed JSON from buffer
            $this->buffer = substr($this->buffer, $i + 1);
            $i = -1; // Restart parsing at the beginning of the buffer
            $len = strlen($this->buffer); // Update length after removing processed part
          }
        }
      }

      // Keep any unprocessed part in the buffer
      return strlen($response);
    });

    if (!empty($this->headers))
      curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
  }

  public function stream(): void
  {
    $this->initStream();
    $this->streaming = true;
    $failures = 0;

    try {
      while ($this->streaming) {
        $start = new Ndate;
        echo "Opening Stream... at " . $start->format(Ndate::DATE_TIME) . "\n";

        $response = curl_exec($this->curl);
        if ($response === false) {
          $this->error = curl_error($this->curl);
          echo "Stream Error: " . $this->error . "\n";
        } else {
          echo "Stream closed with response " . print_r($response, true) . "\n";
        }

        if ($start->secondsUntil(new Ndate) < 5) 
          $failures++; // Reconnecting too quickly
        else 
          $failures = 0; // Reset on longer connections

        if ($failures >= 10)
          throw new StreamRejected();

        // Preparing for the next trial
        $this->initStream();

        // Linear backoff
        $backoff = min(5 * $failures, 30);
        echo "Retrying in $backoff seconds\n";
        sleep($backoff); // Gradually increase up to 30 seconds
      }
    } finally {
      curl_close($this->curl); // Ensure resource closure
    }
  }
}
