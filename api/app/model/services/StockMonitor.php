<?php

class StockMonitor
{
    const BUY = 1;
    const SELL = 2;
    private array $filter_data;
    private string $cookie = '';
    private array $signals;
    private Ndate $refreshed_at;

    private function curl(string $path, string $type, array $data = [], array $headers = []): Response
    {
        if (!isset($this->refreshed_at) || $this->refreshed_at->minutesUntil(new Ndate) > 10) {
            $this->refreshCookie();
            $this->refreshed_at = new Ndate;
        }

        $headers = [
            ...$headers,
            'Content-Type' => 'application/json',
            'Cookie' => $this->cookie,
            'Host' => 'www.members.stockmonitor.com'
        ];

        $curl = new ArteCurl("https://www.members.stockmonitor.com/$path");
        $curl->setHeaders($headers);
        return $curl->send($type, $data);
    }

    public function getConds(int $filter_id)
    {
        $html = $this->curl("signal", "GET", ['sid' => $filter_id])->getBody();

        preg_match("/var signal = jsonParse\((\'|\")(.*?)(\'|\")\)/", $html, $matches);

        return json_decode($matches[2], true)['definition'];
    }

    public function getRunId(int $filter_id)
    {
        $html = $this->curl("signal", "GET", ['sid' => $filter_id])->getBody();
        preg_match("/test-signal-[0-9]+/", $html, $matches);
        return $matches[0];
    }

    public function getSignals(): array
    {
        function getInnerHTML(DOMNode $node)
        {
            $innerHTML = '';
            foreach ($node->childNodes as $child) {
                $innerHTML .= $node->ownerDocument->saveHTML($child);
            }
            return $innerHTML;
        }

        if (isset($this->signals))
            return $this->signals;

        $html = $this->curl("signals/tableSignals/", 'GET')->getBody();
        $html = str_replace(['\&quot;', '\n'], ['"', ''], $html);
        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        $trs = $dom->getElementsByTagName('tr');

        $this->signals = [];
        foreach ($trs as $tr) {
            $td = $tr->getElementsByTagName('td')->item(1);
            if ($td) {
                $a = $td->getElementsByTagName('a')->item(0);
                if ($a) {
                    $href = $a->getAttribute('href');
                    $sid = null;
                    parse_str(parse_url($href, PHP_URL_QUERY), $params);
                    if (isset($params['sid'])) {
                        $sid = intval($params['sid']);
                        $this->signals[] = [
                            'name' => $a->nodeValue,
                            'sid' => $sid
                        ];
                    } else {
                        echo 'Couldn\'t fetch id for signal ' . $a->nodeValue;
                    }
                }
            }
        }

        return $this->signals;
    }

    public function __construct()
    {
    }

    private function refreshCookie()
    {
        $curl = new ArteCurl('https://www.members.stockmonitor.com/auth/login');

        $response = $curl->send('GET');
        foreach ($response->getHeaders() as $key => $value)
            if ($key == 'Set-Cookie')
                $this->cookie = 'conv_source=; ' . $value;

        $curl = new ArteCurl('https://www.members.stockmonitor.com/auth/login_do');
        $curl->setHeaders([
            'Content-Type' => 'application/json',
            'Cookie' => $this->cookie,
            'Host' => 'www.members.stockmonitor.com'
        ]);
        $response = $curl->send("POST", [
            'email' => $_ENV['stockmail'], 'pwd' => $_ENV['stockpwd']
        ]);
    }

    public function getStocks(int $sid, mixed $stock_type, int $limit = null, array $excluded = null): array
    {
        $data = [
            'signalDef' => $this->getConds($sid),
            // 'runId' => $this->getRunId($sid),
            'runId' => "test-signal-1722350933484",
            "symbolsSet" => "us-type-stock",
            "timeframe" => "daily",
            "candleOffset" => "0",
            "is_snippet" => false,
        ];

        $response = $this->curl("signal/test/", "POST", $data);

        $filter_data = [
            'page' => 1,
            "orderState" => [
                "field" => "pchange",
                'dir' => $stock_type == static::BUY || $stock_type == "buy" ? 'DESC' : 'ASC',
            ],
            'runId' => json_decode($response->getBody(), true)['data']['runId']
        ];

        $response = $this->curl("signal/test-result-page", "POST", $filter_data);
        $table_html = json_decode($response->getBody(), true)['html'];
        $stocks = Parser::getDataFromTable($table_html);

        $filters = (new Config)['globals']['excluded'];
        if(!empty($excluded))
            array_push($filters, ...$excluded);

        $stocks = array_filter($stocks, function ($stock) use ($filters) {
            return !in_array($stock['symbol'], $filters);
        });

        if ($limit)
            return array_splice($stocks, 0, $limit);
        else
            return $stocks;
    }
}
