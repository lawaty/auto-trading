<?php

use WebSocket\ConnectionException;
use WebSocket\TimeoutException;

class QuoteStreamer
{
  const TWELVE_DATE_URI = "wss://ws.twelvedata.com/v1/quotes/price?apikey=b2f5dfbf3ef84eb7ba32392be32c58af";
  const FINNHUB_URI = "wss://ws.finnhub.io?token=ct0dlf1r01qkfpo5mfp0ct0dlf1r01qkfpo5mfpg";
  private string $symbol;
  private WebSocket\Client $client;
  private const MAX_RETRIES = 10;
  private string $source;

  public function __construct(string $symbol, string $source = self::TWELVE_DATE_URI)
  {
    $this->symbol = $symbol;
    $this->source = $source;
    $this->client = new WebSocket\Client($source, [
      'timeout' => 30
    ]);
  }

  public function receive(): array
  {
    $retries = 0;

    while (true) {
      try {
        return json_decode($this->client->receive(), true);
      } catch (TimeoutException | ConnectionException $e) {
        $retries++;
        if ($retries > self::MAX_RETRIES) {
          throw new StreamRejected;
        }

        $waitTime = (int)(100 * pow(2, $retries));
        echo "Timeout occurred. Retrying ($retries/" . self::MAX_RETRIES . ") at " . (new Ndate)->format(Ndate::DATE_TIME) . "... Waiting for " . $waitTime . "ms\n";

        usleep($waitTime * 1000);
      }
    }
  }

  private function send(mixed $data): void
  {
    $this->client->send(json_encode($data));
  }

  public function subscribe(): void
  {
    $this->send($this->getMessage());

    $data = $this->receive();
    if ($this->source == self::TWELVE_DATE_URI && $data['status'] !== 'ok') {
      echo "Weird Stream Status\n";
      prettyPrint($data);
      throw new StreamSubscriptionFailed();
    }

    echo "Stream Started Successfully \n";
  }

  private function getMessage(): array
  {
    switch ($this->source) {
      case self::TWELVE_DATE_URI:
        return [
          "action" => "subscribe",
          "params" => [
            "symbols" => $this->symbol
          ]
        ];
      case self::FINNHUB_URI:
        return [
          "type" => "subscribe",
          "symbol" => $this->symbol
        ];
    }
  }
}

class StreamSubscriptionFailed extends Exception {}
