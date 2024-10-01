<?php

class StockMonitor
{
    const BUY = 1;
    const SELL = 2;
    private string $cookie = '';
    private array $signals;

    public function __construct()
    {
        ArteCache::getInst()->install($this, [
            'stockmonitor_cookie' => 'getCookie',
            'conds' => ['getConds', 300],
            'signals' => ['getSignals', 300],
            'runId' => 'getRunId',
            'stocks' => 'getStocks'
        ]);
    }

    private function curl(string $path, string $type, array $data = [], array $headers = [], bool $logging = false): Response
    {
        $this->cookie = ArteCache::getInst()->get('stockmonitor_cookie');

        $headers = [
            ...$headers,
            'Content-Type' => 'application/json',
            'Cookie' => $this->cookie,
            'Host' => 'www.members.stockmonitor.com'
        ];

        $curl = new ArteCurl("https://www.members.stockmonitor.com/$path");
        $curl->setHeaders($headers);
        return $curl->send($type, $data, $logging);
    }

    public function getConds(int $filter_id)
    {
        $html = $this->curl("signal", "GET", ['sid' => $filter_id])->getBody();
        preg_match("/var signal = jsonParse\((\'|\")(.*?)(\'|\")\)/", $html, $matches);
        return json_decode($matches[2], true)['definition'];
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
        /**
         * @var \DOMElement
         */
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

    public function getCookie(): string
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
            'email' => $_ENV['stockmail'],
            'pwd' => $_ENV['stockpwd']
        ]);

        return $this->cookie;
    }

    public function getRunId(int $sid): string
    {
        $cache = ArteCache::getInst();
        $data = [
            'signalDef' => $cache->get('conds', [$sid]),
            'runId' => "test-signal-1722350933484",
            "symbolsSet" => "us-type-stock",
            "timeframe" => "daily",
            "candleOffset" => "0",
            "is_snippet" => false,
        ];

        return $this->curl("signal/test/", "POST", $data)->getBody();
    }

    public function getStocks(int $sid, mixed $stock_type, int $limit = null, array $excluded = null, array $included = null, string $dir = null): array
    {
        $cache = ArteCache::getInst();
        echo "Fetching Stocks... \n";
        if(!$dir)
            $dir = $stock_type == static::BUY || $stock_type == "buy" ? 'DESC' : 'ASC';

        $filter_data = [
            'page' => 1,
            "orderState" => [
                "field" => "pchange",
                'dir' => $dir,
            ],
            'runId' => json_decode($cache->get('runId', [$sid]), true)['data']['runId']
        ];

        var_dump($dir);

        $response = $this->curl("signal/test-result-page", "POST", $filter_data);
        $table_html = json_decode($response->getBody(), true)['html'];
        $stocks = Parser::getDataFromTable($table_html);

        if(!$excluded)
            $excluded = [];

        array_push($excluded, ...json_decode(file_get_contents(JSONS_DIR .'/currently_trading.json'), true));
        
        $filters = Config::getInst()['globals']['excluded'];
        if (!empty($excluded))
            array_push($filters, ...$excluded);

        $stocks = array_filter($stocks, function ($stock) use ($filters) {
            return !in_array($stock['symbol'], $filters);
        });

        if (!empty($included))
            $stocks = array_filter($stocks, function ($stock) use ($included) {
                return in_array($stock['symbol'], $included);
            });

        if ($limit)
            return array_splice($stocks, 0, $limit);

        return $stocks;
    }
}
