<?php
class TradeStation
{
    private string $api_key = 'lMujDTbmHrqZ5a7EkPSyskivY9doHu54';
    private string $api_secret = 'AIK6Sot-Nz_QbwbOUuL5TuYZx741S978Jnie3pRQUt7PwusNBA4KdbcV8epUdRzg';
    private string $refresh_token = 'T06fxV7y9MGVv1nk0EkFu9JjQ3wYt04lNWNP51lx9A8RX';
    public string $access_token;
    public string $live_trade_api_base = "https://api.tradestation.com/v3";
    public string $sim_trade_api_base = "https://sim-api.tradestation.com/v3";
    public string $api_base;
    private string $account_id = "";
    private $params;
    private string $which;
    private Ndate $refreshed_at;

    public function __construct($which)
    {
        $this->which = $which;
        $this->refreshSettings();
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

    public function curl(string $url, string $type, array $data = [], array $headers = []): mixed
    {
        if (!isset($this->refreshed_at) || $this->refreshed_at->minutesUntil(new Ndate) > 10) {
            $this->refreshToken();
            $this->refreshed_at = new Ndate;
        }

        if (!isset($headers['Authorization']) && isset($this->access_token))
            $headers['Authorization'] = "Bearer " . $this->access_token;

        $curl = new ArteCurl($url);
        $curl->setHeaders($headers);
        return $curl->send($type, $data)->getBody();
    }

    public function refreshSettings()
    {
        $json_data = file_get_contents(JSONS_DIR . "/params.json");
        $this->params = json_decode($json_data, true);
        $this->account_id = $this->params['globals']['account_id'];
        if ($this->params['globals']['is_live'] == "0")
            $this->api_base = $this->sim_trade_api_base;
        else if ($this->params['globals']['is_live'] == "1")
            $this->api_base = $this->live_trade_api_base;
    }

    public function authenticate()
    {
        $authorizing_url = 'https://signin.tradestation.com/authorize';
        $data = [
            'response_type' => "code",
            'client_id' => $this->api_key,
            'redirect_uri' => 'https://auto-trading.drolez-apps.cloud/',
            'scope' => 'openid profile offline_access MarketData ReadAccount Trade',
            'audience' => 'https://api.tradestation.com',
            'state' => 'wow_idk_12345'
        ];
        $curl = new ArteCurl($authorizing_url);
        $curl->send("GET", $data);
    }

    public function getAccessToken()
    {
        $curl = new ArteCurl('https://signin.tradestation.com/oauth/token');
        $curl->setHeaders([
            'Content-Type:application/x-www-form-urlencoded'
        ]);
        $response = $curl->send("POST", [
            'grant_type' => "authorization_code",
            'client_id' => $this->api_key,
            'client_secret' => $this->api_secret,
            'redirect_uri' => 'https://auto-trading.drolez-apps.cloud/',
            'code' => 'GwrN5QHRjJ_-ppDb',
            'state' => 'wow_idk_12345'
        ]);

        $this->access_token = $response->getBody()['access_token'];
    }

    public function refreshToken()
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
        $
        $this->access_token = $this->curl($refresh_url, "POST", $data, $header);
    }

    public function getBalance()
    {
        $account_id = $this->account_id;
        $balance_url = $this->api_base . "/brokerage/accounts/$account_id/balances";
        $headers = [
            'Authorization' => "Bearer " . $this->access_token
        ];
        $response = $this->curl($balance_url, "GET", [], $headers);

        if ($response['Error'] && str_contains($response['Message'], "Invalid Account ID"))
            throw new InvalidAccountID();
        return $response['Balances'][0]['BuyingPower'];
    }

    public function getAccounts()
    {
        $get_accounts_url = $this->api_base . "/brokerage/accounts";

        $response = $this->curl($get_accounts_url, 'GET');
        return $response;
    }

    public function getPerStockPower()
    {
        $ordering_power = $this->params['globals']['money'];
        if ($this->params['globals']['is_full_power'] == "1")
            $ordering_power = $this->getBalance();
        $trades_number = $this->params[$this->which]["number_of_trades"];
        $per_stock_power = $ordering_power / $trades_number;
        return $per_stock_power;
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

        if (isset($response['Errors'])) {
            var_dump("An error has occured");
            var_dump($response);
        } else {
            return max($response["Confirmations"][0]['EstimatedPrice'] ?? 0, 1);
        }
    }

    private function getExecutionPrice($order_id)
    {
        $account_id = $this->account_id;
        $execution_url = $this->api_base . "/brokerage/accounts/$account_id/orders";
        $response = $this->curl($execution_url, 'GET');
        $execution_price = $response->Orders[0]->Legs->ExecutionPrice;
        return $execution_price;
    }

    public function placeOrder($stock, $order_type, $trade_action, $percent = 0): ?int
    {
        $percent += 1;

        $account_id = $this->account_id;
        $ordering_url = $this->api_base . "/orderexecution/orders";
        $per_stock_power = $this->getPerStockPower();

        $stock_quantity = $stock['quantity'] ?? floor($per_stock_power / $stock['price']);
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
        $response = $this->curl($ordering_url, "POST", $data);

        if (isset($response['Errors'])) {
            print_r($response);
            echo "\n";
            return null;
        }

        if (isset($response['Orders'][0]['OrderID']))
            StockLogger::logStock(
                ucfirst($this->which),
                "$order_type $trade_action",
                [
                    ...$stock,
                    'OrderID' => $response['Orders'][0]['OrderID'] ?? -1,
                    'price' => $stock_price
                ]
            );

        return $response['Orders'][0]['OrderID'] ?? null;
    }

    public function placeOCO($stock, array $operations)
    {
        $account_id = $this->account_id;
        $ordering_url = $this->api_base . "/orderexecution/ordergroups";
        $per_stock_power = $this->getPerStockPower();
        $payload = [
            'Type' => 'OCO',
            'Orders' => []
        ];

        foreach ($operations as $i => $operation) {
            $stock_quantity = $stock['quantity'] ?? floor($per_stock_power / $stock['price']);

            $operation['percent'] += 1;

            $price = round($stock['price'] * $operation['percent'], 2);
            $order_data = [
                "AccountID" => $account_id,
                "Symbol" => $stock['symbol'],
                "Quantity" => "$stock_quantity",
                "OrderType" => $operation['order_type'],
                "TradeAction" => $operation['trade_action'],
                "TimeInForce" => ["Duration" => "DAY"],
                "Route" => "Intelligent",
            ];
            if ($operation['order_type'] == 'StopMarket')
                $order_data["StopPrice"] = "$price";
            else if ($operation['order_type'] == 'Limit')
                $order_data['LimitPrice'] = "$price";

            $payload['Orders'][] = $order_data;
            $operations[$i]['price'] = $price;
        }

        $response = $this->curl($ordering_url, "POST", $payload);
        // var_dump($response);

        if (isset($response['Errors'])) {
            print_r($response);
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

    public function editOrder($stock, $order_id, $data)
    {
        $put_url = $this->api_base . "/orderexecution/orders/" . $order_id;
        $headers = [
            'Content-Type: application/json'
        ];

        $temp = $stock;
        if (isset($data['percent'])) {
            $data['percent'] += 1;
            $temp['price'] = round($data['percent'] * $stock['price'], 2);
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

        $response = $this->curl($put_url, 'PUT', $data, $headers);

        return $response['Orders'][0]['OrderID'] ?? null;
    }

    public function order($stocks, $order_type, $trade_action): array
    {
        $account_id = $this->account_id;
        $ordering_url = $this->api_base . "/orderexecution/orders";

        $failures = [];
        foreach ($stocks as $stock) {
            $per_stock_power = $this->getPerStockPower();
            $stock_quantity = floor($per_stock_power / $stock['price']);
            $stock_price = round($stock['price'], 2);
            $data = [
                "AccountID" => $account_id,
                "Symbol" => $stock['symbol'],
                "Quantity" => "$stock_quantity",
                "OrderType" => $order_type,
                "TradeAction" => $trade_action,
                "TimeInForce" => ["Duration" => "DAY"],
                "Route" => "Intelligent",
                "LimitPrice" => "$stock_price"
            ];
            $response = $this->curl($ordering_url, "POST", $data);

            $exec_quantity = $this->getExecutedQuantity($response, $stock_price);
            print_r("Stock Quantity: " . $stock_quantity . "\n");
            print_r("EXECUTED Stock Quantity: " . $exec_quantity . "\n");
            if ($exec_quantity != $stock_quantity)
                $failures[] = $stock['symbol'];
        }
        return $failures;
    }

    public function getOrderStatus(int $order_id)
    {
        return $this->getOrder($order_id)['Status'] ?? null;
    }

    public function getOrder(int $order_id)
    {
        $order_uri = "$this->api_base/brokerage/accounts/$this->account_id/orders/$order_id";

        $response = $this->curl($order_uri, "GET");

        return $response['Orders'][0] ?? null;
    }

    public function getExecQuantity($order_id)
    {
        $account_id = $this->account_id;
        $exec_url = $this->api_base . "/brokerage/accounts/$account_id/orders/" . $order_id;

        $response = $this->curl($exec_url, "GET");
        $exec_quantity = $response["Orders"][0]['Legs'][0]['ExecQuantity'];

        return $exec_quantity;
    }

    public function getExecutedQuantity($order, $limit_price)
    {
        $account_id = $this->account_id;
        $order = $order['Orders'][0];
        $exec_url = $this->api_base . "/brokerage/accounts/$account_id/orders/" . $order['OrderID'];

        $response = $this->curl($exec_url, "GET");
        $exec_quantity = $response["Orders"][0]['Legs'][0]['ExecQuantity'];

        return $exec_quantity;
    }
    public function replaceOrder($exec_quantity, $order, $limit_price)
    {
        $put_url = $this->api_base . "/orderexecution/orders/" . $order['OrderID'];
        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ];
        $data = [
            'Quantity' => "$exec_quantity",
            'LimitPrice' => "$limit_price"
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $put_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error occurred: $error_msg");
        }

        curl_close($ch);

        $response = json_decode($response, true);

        var_dump($response['Message']);
    }

    public function stopLoss($stocks, $dec_pct, $order_type, $trade_action)
    {
        $account_id = $this->account_id;
        $ordering_url = $this->api_base . "/orderexecution/orders";
        foreach ($stocks as $stock) {
            $stop_price = round($stock['price'] * $dec_pct, 2);

            if ($trade_action == 'BUYTOCOVER')
                $stop_price = ceil($stop_price);

            $per_stock_power = $this->getPerStockPower();
            $stock_quantity = $stock['quantity'] ?? floor($per_stock_power / $stock['price']);
            $data = [
                "AccountID" => $account_id,
                "Symbol" => $stock['symbol'],
                "Quantity" => "$stock_quantity",
                "OrderType" => $order_type,
                "TradeAction" => $trade_action,
                "TimeInForce" => ["Duration" => "GTC"],
                "Route" => "Intelligent",
                "StopPrice" => "$stop_price",
            ];
            $this->curl($ordering_url, "POST", $data);
        }
    }
}

class InvalidAccountID extends Exception
{
}
