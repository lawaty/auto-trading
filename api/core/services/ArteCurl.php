<?php

class ArteCurl
{
  private string $url;
  private ?CurlHandle $curl;
  private array $headers = [];
  private array $response_headers = [];
  private string $error = '';

  public function __construct(string $endpoint)
  {
    $this->url = $endpoint;
    $this->curl = curl_init();
  }

  public function setHeaders(array $headers): void
  {
    foreach ($headers as $key => $value)
      $this->headers[] = "$key: $value";
  }

  public function send($request_type, $data = []): Response
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

    return new Response($response, $http_status_code, $this->response_headers);
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

  public function stream($url, $callback)
  {
    curl_setopt($this->curl, CURLOPT_URL, $url);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($this->curl, CURLOPT_BUFFERSIZE, 128);

    $continue = true;

    curl_setopt($this->curl, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($callback, &$continue) {
      $lines = explode("\n", $data);
      foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
          $result = call_user_func($callback, $line);
          if ($result === false) {
            $continue = false;
            break;
          }
        }
      }
      return strlen($data);
    });

    if (!empty($this->headers)) {
      curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
    }

    while ($continue) {
      curl_exec($this->curl);
      if (curl_errno($this->curl)) {
        throw new Exception('cURL error: ' . curl_error($this->curl));
        break;
      }
      usleep(100000);
    }
    $this->closeCurl();
  }
}
