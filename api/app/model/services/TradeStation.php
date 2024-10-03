<?php

const BUYING_TOLERANCE = 0.98;

class TradeStation
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

    public function __construct($which)
    {
        $this->which = $which;
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
        $orders = $this->curl($uri, "GET")['Orders'];
        $res = [];
        foreach ($orders as $order) {
            $desired = true;
            foreach ($filters as $key => $value) {
                if (
                    $key == 'Action' && $order['Legs'][0]['BuyOrSell'] != $value ||
                    $key != 'Action' && $order[$key] != $value
                ) {
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
        return $this->curl("{$this->api_base}/orderexecution/orders/$order_id", "DELETE");
    }

    public function curl(string $url, string $type, array $data = [], array $headers = [], $logging = false): mixed
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

    public function loadSettings()
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
        $response = $curl->send("POST", $data)->getBody();
        $this->access_token = json_decode($response)->access_token;
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

    public function getBuyingPower()
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

    public function getStockEstimatedPrice($stock_symbol, $order_type, $trade_action)
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

    public function placeOrder($stock, $order_type, $trade_action, $quantity = null, $percent = 0): ?int
    {
        $percent += 1;

        $account_id = $this->account_id;
        $ordering_url = $this->api_base . "/orderexecution/orders";

        $stock_quantity = $stock['quantity'] ?? $quantity;
        if ($stock_quantity == 0)
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
            $data['StopPrice'] = "$stock_price";

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

            if ($operation['percent'] < 1)
                $price = floor($operation['percent'] * $stock['price'] * 100) / 100;
            else
                $price = ceil($operation['percent'] * $stock['price'] * 100) / 100;

            $price = number_format($price, 2, '.', '');


            echo "{$operation['order_type']} {$operation['trade_action']} with price {$stock['price']} at " . (new Ndate)->format(Ndate::DATE_TIME) . " \n";

            echo "\n\n";
            var_dump($operation['order_type'] . " " . $operation['trade_action'], $operation['percent'], $stock['price'], $price);
            echo "\n\n";

            $order_data = [
                "AccountID" => $account_id,
                "Symbol" => $stock['symbol'],
                "Quantity" => "$stock_quantity",
                "OrderType" => $operation['order_type'],
                "TradeAction" => $operation['trade_action'],
                "TimeInForce" => ["Duration" => "DAY"],
                "Route" => "Intelligent"
            ];

            if ($operation['order_type'] == 'StopMarket')
                $order_data["StopPrice"] = "$price";
            else if ($operation['order_type'] == 'Limit')
                $order_data['LimitPrice'] = "$price";

            $payload['Orders'][] = $order_data;
            $operations[$i]['price'] = $price;
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
                $operations_str[] = "{$operation['order_type']} {$operation['trade_action']}";

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
        if (!isset($data['order_type']) || !isset($data['trade_action']))
            throw new InvalidArguments("Data must have order_type and trade_action parameters");

        $put_url = $this->api_base . "/orderexecution/orders/" . $order_id;
        $headers = [
            'Content-Type: application/json'
        ];

        $temp = $stock;
        if (isset($data['percent'])) {
            $data['percent'] += 1;
            if ($data['percent'] < 1)
                $temp['price'] = floor($data['percent'] * $stock['price'] * 100) / 100;
            else
                $temp['price'] = ceil($data['percent'] * $stock['price'] * 100) / 100;

            $temp['price'] = number_format($temp['price'], 2, '.', '');

            echo "{$data['order_type']} {$data['trade_action']} with price {$temp['price']} at " . (new Ndate)->format(Ndate::DATE_TIME) . " \n";

            echo "\n\n";
            var_dump($data['order_type'] . " " . $data['trade_action'], $data['percent'], $stock['price'], $temp['price']);
            echo "\n\n";

            if ($data['order_type'] == 'Limit')
                $data['LimitPrice'] = "{$temp['price']}";
            else if ($data['order_type'] == 'StopMarket')
                $data['StopPrice'] = "{$temp['price']}";

            unset($data['percent']);
        }

        StockLogger::logStock(
            ucfirst($this->which),
            "Replaced {$data['order_type']} {$data['trade_action']}",
            $temp
        );

        unset($data['trade_action']);
        unset($data['order_type']);

        $response = $this->curl($put_url, 'PUT', $data, $headers, true);

        $error = $response['Errors'] ?? $response['Error'] ?? null;
        if ($error) {
            echo __FUNCTION__ . "\n";
            prettyPrint($data);
            prettyPrint($response);
        }

        return $response['Orders'][0]['OrderID'] ?? null;
    }

    public function getOrderStatus(int $order_id)
    {
        return $this->getOrder($order_id)['Status'] ?? null;
    }

    public function getOrder(int $order_id)
    {
        $order_uri = "$this->api_base/brokerage/accounts/$this->account_id/orders/$order_id";

        $response = $this->curl($order_uri, "GET", [], [], true); // debugging

        return $response['Orders'][0] ?? null;
    }

    public function getExecQuantity($order_id)
    {
        $account_id = $this->account_id;
        $exec_url = $this->api_base . "/brokerage/accounts/$account_id/orders/" . $order_id;

        $response = $this->curl($exec_url, "GET");
        $exec_quantity = $response["Orders"][0]['Legs'][0]['ExecQuantity'] ?? 0;

        return $exec_quantity;
    }
}

class InvalidAccountID extends Exception {}

class InsufficientMoney extends Exception {}

class MissingNumberOfTrades extends Exception {}
