<?php

const BUYING_TOLERANCE = 0.98;

class TradeStation implements ITradeStation
{
    private string $api_key = 'lMujDTbmHrqZ5a7EkPSyskivY9doHu54';
    private string $api_secret = 'AIK6Sot-Nz_QbwbOUuL5TuYZx741S978Jnie3pRQUt7PwusNBA4KdbcV8epUdRzg';
    private string $refresh_token = 'T06fxV7y9MGVv1nk0EkFu9JjQ3wYt04lNWNP51lx9A8RX';
    public string $access_token;
    public string $live_trade_api_base = "https://api.tradestation.com/v3";
    public string $sim_trade_api_base = "https://sim-api.tradestation.com/v3";
    public string $api_base;
    public string $account_id = "";
    private array $params;
    private string $which;
    private string $close_position;

    public function __construct($which)
    {
        $this->which = $which;
        if ($which == 'buy')
            $this->close_position = "SELL";
        else
            $this->close_position = "BUYTOCOVER";

        $this->loadSettings();
        $this->installCache();
    }

    public function installCache(): void
    {
        ArteCache::getInst()->install($this, [
            'tradestation_access_token' => ['getAccessToken', 150],
            'today_orders' => 'getTodayOrders',
            'balance' => 'getBalance',
            'buying_power' => 'getBuyingPower',
            'stock' => 'getStock',
            'stock_price' => 'getStockEstimatedPrice',
            'order' => 'getOrder',
            'order_quantity' => 'getExecQuantity'
        ]);
    }

    public function getTodayOrders(array $filters = [])
    {
        $uri = $this->api_base . "/brokerage/accounts/$this->account_id/orders";
        $orders = $this->curl($uri, "GET", [], [], true)['Orders'];
        $res = [];
        foreach ($orders as $order) {
            $desired = true;
            foreach ($filters as $key => $value) {
                if (
                    $key == 'Action' && $order['Legs'][0]['BuyOrSell'] != $value ||
                    isset($order[$key]) && $order[$key] != $value ||
                    isset($order['Legs'][0][$key]) && $order['Legs'][0][$key] != $value
                ) {
                    var_dump($order['Legs'][0][$key], $value);
                    echo "<br>";
                    $desired = false;
                    break;
                }
            }
            if (!$desired)
                continue;

            if ((new Ndate($order['OpenedDateTime']))->format() == (new Ndate)->format())
                $res[] = $order;
        }

        return $res;
    }

    public function cancel(int $order_id)
    {
        return $this->curl("{$this->api_base}/orderexecution/orders/$order_id", "DELETE", [], [], true);
    }

    // private function curl(string $url, string $type, array $data = [], array $headers = [], $logging = false): mixed
    public function curl(string $url, string $type, array $data = [], array $headers = [], $logging = true): mixed // stub: logging set to true for debugging
    {
        $this->access_token = ArteCache::getInst()->get('tradestation_access_token');

        if (!isset($headers['Authorization']) && isset($this->access_token))
            $headers['Authorization'] = "Bearer " . $this->access_token;

        if (!str_contains($url, $this->api_base))
            $url = $this->api_base . "/$url";

        $curl = new ArteCurl($url);
        $curl->setHeaders($headers);
        $response = $curl->send($type, $data, $logging)->getBody();
        if (isJson($response))
            $response =  json_decode($response, true);

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error)
            var_dump($response);

        if ($error == 'TooManyRequests') {
            sleep(10);
            return $this->curl($url, $type, $data, $headers);
        }

        return $response;
    }

    private function loadSettings()
    {
        $this->params = Config::getInst()->toArray();
        $this->account_id = $this->params['globals']['account_id'];
        if ($this->params['globals']['is_live'] == "0")
            $this->api_base = $this->sim_trade_api_base;
        else if ($this->params['globals']['is_live'] == "1")
            $this->api_base = $this->live_trade_api_base;
    }

    public function getAccessToken(): string
    {
        $refresh_url = 'https://signin.tradestation.com/oauth/token';
        $header = [
            'Content-Type:application/x-www-form-urlencoded'
        ];
        $data = [
            'grant_type' => "refresh_token",
            'client_id' => $this->api_key,
            'client_secret' => $this->api_secret,
            'refresh_token' => $this->refresh_token,
        ];

        $curl = new ArteCurl($refresh_url);
        $curl->setHeaders($header);
        $response = json_decode($curl->send("POST", $data, true)->getBody());
        if (isset($response->error) && $response->error == 'access_denied')
            throw new Exception("Unauthorized API Key");

        $this->access_token = $response->access_token;
        return $this->access_token;
    }

    public function getBalance(): ?float
    {
        $account_id = $this->account_id;
        $balance_url = $this->api_base . "/brokerage/accounts/$account_id/balances";
        $response = $this->curl($balance_url, "GET");

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error && str_contains($response['Message'], "Invalid Account ID"))
            throw new InvalidAccountID();
        else if ($error) {
            return null;
        }

        return $response['Balances'][0]['CashBalance'] ?? null;
    }

    public function getEquity(): ?float
    {
        $account_id = $this->account_id;
        $balance_url = $this->api_base . "/brokerage/accounts/$account_id/balances";
        $response = $this->curl($balance_url, "GET");

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error && str_contains($response['Message'], "Invalid Account ID"))
            throw new InvalidAccountID();
        else if ($error) {
            return null;
        }

        return $response['Balances'][0]['Equity'] ?? null;
    }

    public function getBuyingPower(): null|float
    {
        $account_id = $this->account_id;
        $balance_url = $this->api_base . "/brokerage/accounts/$account_id/balances";
        $response = $this->curl($balance_url, "GET");

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error && isset($response['Message']) && str_contains($response['Message'], "Invalid Account ID"))
            throw new InvalidAccountID();
        else if ($error) {
            var_dump($error);
        }

        $power = $response['Balances'][0]['BuyingPower'];
        if ($power < 0)
            $power = 0;

        return $power;
    }

    public function getStock(string $symbol): ?array
    {
        return $this->curl("marketdata/quotes/$symbol", "GET")['Quotes'][0] ?? null;
    }

    public function getStockEstimatedPrice($stock_symbol, $order_type, $trade_action): null|float
    {
        $account_id = $this->account_id;
        $execution_url = $this->api_base . "/orderexecution/orderconfirm";
        $headers = [
            'content-type' => 'application/json'
        ];

        $data = [
            "AccountID" => $account_id,
            "Symbol" => $stock_symbol,
            "Quantity" => "1",
            "OrderType" => $order_type,
            "TradeAction" => $trade_action,
            "TimeInForce" => ["Duration" => "DAY"],
            "Route" => "Intelligent"
        ];

        if ($this->which == 'short')
            $data['TradeAction'] = 'SELLSHORT';

        $response = $this->curl($execution_url, "POST", $data, $headers);

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error) {
            echo __FUNCTION__ . "\n";
            var_dump("An error has occured");
            var_dump($response);
        } else {
            return max($response["Confirmations"][0]['EstimatedPrice'] ?? 0, 1);
        }
    }

    public function placeOrder(array $stock, string $order_type, string $trade_action, array $args = []): ?int
    {
        $percent = $args['percent'] ?? 0;
        $quantity = $args['quantity'] ?? null;

        $percent += 1;

        $account_id = $this->account_id;
        $ordering_url = $this->api_base . "/orderexecution/orders";

        $stock_quantity = $stock['quantity'] ?? $quantity;
        if ($stock_quantity === 0)
            throw new InsufficientMoney;

        if ($order_type != 'Market')
            $stock_price = round($stock['price'] * $percent, 2);

        $data = [
            "AccountID" => $account_id,
            "Symbol" => $stock['symbol'],
            "Quantity" => "$stock_quantity",
            "OrderType" => $order_type,
            "TradeAction" => $trade_action,
            "TimeInForce" => ["Duration" => "DAY"],
            "Route" => "Intelligent",
        ];

        if ($order_type == 'Limit')
            $data['LimitPrice'] = "$stock_price";

        else if ($order_type == 'StopMarket')
            if (isset($args['trailStop']))
                $data['AdvancedOptions'] = [
                    'TrailingStop' => [
                        'Percent' => round(abs($percent - 1) * 100, 2),
                    ]
                ];
            else {
                if ($this->which == 'buy')
                    $rounded = ceil($stock_price * 100) / 100;
                else
                    $rounded = floor($stock_price * 100) / 100;

                $data['StopPrice'] = number_format($rounded, 2, '.', '');
            }

        // echo "\n$order_type $trade_action:\n";
        // print_r($data);
        // echo "\n";
        $response = $this->curl($ordering_url, "POST", $data, [], true);

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error) {
            echo __FUNCTION__ . "\n";
            prettyPrint($data);
            prettyPrint($response);
            echo "\n";
            return null;
        }

        echo "$order_type $trade_action with price " . ($stock_price ?? 'N/A') . " at " . (new Ndate)->format(Ndate::DATE_TIME) . " \n";

        if (isset($response['Orders'][0]['OrderID']))
            StockLogger::logStock(
                ucfirst($this->which),
                "$order_type $trade_action",
                [
                    ...$stock,
                    'OrderID' => $response['Orders'][0]['OrderID'] ?? -1,
                    'price' => '-'
                ]
            );

        return $response['Orders'][0]['OrderID'] ?? null;
    }

    public function placeOCO($stock, array $operations)
    {
        $account_id = $this->account_id;
        $ordering_url = $this->api_base . "/orderexecution/ordergroups";

        $payload = [
            'Type' => 'OCO',
            'Orders' => []
        ];

        foreach ($operations as $i => $operation) {
            $stock_quantity = $stock['quantity'] ?? $operations['quantity'];

            if (!isset($operation['percent']))
                $operation['percent'] = 0;

            $operation['percent'] += 1;

            $adjusted_price = $stock['price'] * $operation['percent'];

            if ($operation['OrderType'] != 'StopMarket')
                $price = round($adjusted_price, 2);
            else {
                if ($this->which == 'buy')
                    $price = ceil($adjusted_price * 100) / 100;
                else
                    $price = floor($adjusted_price * 100) / 100;
            }

            $formatted = number_format($price, 2, '.', '');

            echo "{$operation['OrderType']} {$operation['TradeAction']} with price $formatted at " . (new Ndate)->format(Ndate::DATE_TIME) . " \n";

            $order_data = [
                "AccountID" => $account_id,
                "Symbol" => $stock['symbol'],
                "Quantity" => "$stock_quantity",
                "OrderType" => $operation['OrderType'],
                "TradeAction" => $operation['TradeAction'],
                "TimeInForce" => ["Duration" => "DAY"],
                "Route" => "Intelligent"
            ];

            if ($operation['OrderType'] == 'StopMarket') {
                $trail_price = number_format(max(abs($price - $stock['price']), 0.01), 2, '.', '');
                // $order_data["StopPrice"] = number_format($formatted, 2, '.', '');
                $order_data['AdvancedOptions'] = [
                    'TrailingStop' => [
                        'Percent' => round(abs($operation['percent'] - 1) * 100, 2),
                    ]
                ];

                echo "TrailingStop: $trail_price\n";
            } else if ($operation['OrderType'] == 'Limit')
                $order_data['LimitPrice'] = "$formatted";

            $payload['Orders'][] = $order_data;
            $operations[$i]['price'] = $formatted;
        }

        $response = $this->curl($ordering_url, "POST", $payload, [], true);
        // var_dump($response);

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error) {
            echo __FUNCTION__ . "\n";
            prettyPrint($payload);
            prettyPrint($response);
            echo "\n";
            return null;
        }

        if (isset($response['Orders'][0]['OrderID'])) {
            $operations_str = [];
            foreach ($operations as $operation)
                $operations_str[] = "{$operation['OrderType']} {$operation['TradeAction']}";

            $operations_str = implode(', ', $operations_str);

            StockLogger::logStock(
                ucfirst($this->which),
                "OCO $operations_str",
                [
                    ...$stock,
                    'OrderIDs' => implode(', ', array_column($response['Orders'], 'OrderID')),
                    'price' => implode(', ', array_column($operations, 'price'))
                ]
            );
        } else {
            echo "Weird Response in OCO";
            var_dump($response);
        }

        return array_column($response['Orders'], 'OrderID') ?? null;
    }

    public function editOrder(array $stock, int $order_id, array $data): ?int
    {
        if (!isset($data['OrderType']) || !isset($data['TradeAction']))
            throw new InvalidArguments("Data must have OrderType and TradeAction parameters");

        $put_url = $this->api_base . "/orderexecution/orders/" . $order_id;
        $headers = [
            'Content-Type: application/json'
        ];

        $temp = $stock;
        if (isset($data['percent'])) {
            $data['percent'] += 1;
            if ($data['percent'] < 1)
                $temp['price'] = ceil($data['percent'] * $stock['price'] * 100) / 100;
            else
                $temp['price'] = floor($data['percent'] * $stock['price'] * 100) / 100;

            $temp['price'] = number_format($temp['price'], 2, '.', '');

            if ($data['OrderType'] == 'Limit')
                $data['LimitPrice'] = "{$temp['price']}";
            else if ($data['OrderType'] == 'StopMarket')
                $data['StopPrice'] = "{$temp['price']}";

            unset($data['percent']);
        }

        StockLogger::logStock(
            ucfirst($this->which),
            "Replaced {$data['OrderType']} {$data['TradeAction']}",
            $temp
        );

        // unset($data['TradeAction']);
        // unset($data['OrderType']);

        $response = $this->curl($put_url, 'PUT', $data, $headers, true);

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error) {
            echo __FUNCTION__ . "\n";
            prettyPrint($data);
            prettyPrint($response);
            throw new OrderFailed($error);
        }

        return $response['OrderID'] ?? $response['Orders'][0]['OrderID'] ?? null;
    }

    public function getOrderStatus(int $order_id)
    {
        return $this->getOrder($order_id)['Status'] ?? null;
    }

    public function getOrder(int $order_id)
    {
        $order_uri = "$this->api_base/brokerage/accounts/$this->account_id/orders/$order_id";

        $response = $this->curl($order_uri, "GET"); // debugging

        return $response['Orders'][0] ?? null;
    }

    public function getExecQuantity($order_id): int
    {
        $account_id = $this->account_id;
        $exec_url = $this->api_base . "/brokerage/accounts/$account_id/orders/" . $order_id;

        $response = $this->curl($exec_url, "GET");
        $exec_quantity = $response["Orders"][0]['Legs'][0]['ExecQuantity'] ?? 0;

        return $exec_quantity;
    }

    public function closePosition(array $trade): void
    {
        if (isset($trade['stop_id']))
            $this->cancel($trade['stop_id']);

        $this->placeOrder($trade, 'Market', $this->close_position, ['quantity' => $trade['quantity']]);
    }

    public function getStreamer(string $csv_symbols): ArteCurl
    {
        $streamer = new ArteCurl("$this->api_base/marketdata/stream/quotes/$csv_symbols");

        $streamer->setHeaders([
            'Authorization' => "Bearer " . $this->getAccessToken()
        ]);

        return $streamer;
    }
}

class InvalidAccountID extends Exception {}

class InsufficientMoney extends Exception {}

class MissingNumberOfTrades extends Exception {}
