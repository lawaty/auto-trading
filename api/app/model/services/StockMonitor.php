<?php

class StockMonitor
{
    const BUY = 1;
    const SELL = 2;
    private array $filter_data;
    private $test_signal;
    private string $cookie = '';
    private array $signals;

    private function curl(string $path, string $type, array $data = [], array $headers = []): Response
    {
        $this->refreshCookie();

        $headers = [
            ...$headers,
            'Content-Type' => 'application/json',
            'Content-Length' => strlen(json_encode($this->test_signal)),
            'Cookie' => $this->cookie,
            'Host' => 'www.members.stockmonitor.com'
        ];
        $curl = new ArteCurl("https://www.members.stockmonitor.com/$path");
        $curl->setHeaders($headers);
        return $curl->send($type, $data);
    }

    public function getSignals(): array
    {
        if (isset($this->signals))
            return $this->signals;

        $path = "signals";
        $html = $this->curl($path, 'GET')->getBody();

        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $div = $dom->getElementById('table-signals');
        $trs = $div->getElementsByTagName('tr');

        $this->signals = [];
        foreach ($trs as $tr) {
            $td = $tr->getElementsByTagName('td')->item(1);
            $a = $td->getElementsByTagName('a')->item(0);

            $href = $a->getAttribute('href');
            $sid = null;
            parse_str(parse_url($href, PHP_URL_QUERY), $params);
            if (!isset($params['sid'])) {
                echo 'Couldn\'t fetch id for signal ' . $a->nodeValue;
                continue;
            }

            $sid = $params['sid'];

            $this->signals[] = [
                'name' => $a->nodeValue,
                'sid' => $sid
            ];
        }

        return $this->signals;
    }

    public function __construct()
    {
        $this->filter_data =  [
            'page' => 1,
            "orderState" => ["field" => "pchange"]
        ];
    }

    private function refreshCookie()
    {
        $curl = new ArteCurl('https://www.members.stockmonitor.com/auth/login');

        $response = $curl->send('GET');

        foreach ($response->getHeaders() as $hdr) {
            if (stripos($hdr, 'Set-Cookie:') === 0) {
                $this->cookie = 'conv_source=; ' . explode(';', trim(substr($hdr, 11)))[0];
            }
        }

        $this->curl('auth/login_do/', 'POST', [
            'email' => $_ENV['stockmail'], 'pwd' => $_ENV['stockpwd']
        ]);
    }
    public function initFilter(int $sid)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Length' => strlen(json_encode($this->test_signal)),
            'Cookie' => $this->cookie,
            'Host' => 'www.members.stockmonitor.com'
        ];

        $data = [
            'signalDef' => Parser::getConds($sid, $this->cookie),
            'runId' => Parser::getRunId($sid, $this->cookie),
            "symbolsSet" => "us-type-stock",
            "timeframe" => "daily",
            "candleOffset" => "0",
            "is_snippet" => false,
        ];

        $response = $this->curl("signal/test/", "POST", $data, $headers)->getBody();

        return $response['success'] ? true : false;
    }
    public function getStocks(int $stock_type): array
    {

        if ($stock_type == static::BUY) {
            $this->filter_data['runId'] = $this->test_signal->runId;
            $this->filter_data['orderState']['dir'] = 'DESC';
        } else if ($stock_type == static::SELL) {
            $this->filter_data['runId'] = $this->test_signal->runId;
            $this->filter_data['orderState']['dir'] = 'ASC';
        }

        $table = $this->curl("signal/test-result-page", "GET", $this->filter_data)->getBody();

        return Parser::getDataFromTable($table['html']);
    }
}
