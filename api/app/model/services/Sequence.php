<?php

const CLOSING_TOLERANCE = 5; // mins

class Sequence
{
    const FILLED = 1;
    const STOPLOSS = 2;
    const TIMEOUT = 3;

    private string $which;
    private array $stock;
    private Config $config;
    private TradeStation $trade_station;

    private Ndate $bell;
    private array $stages;
    private string $close_position;
    private int $status = -1;
    private float $filled_price;

    private function parseArgs(array $args): void
    {
        $this->stages = $args['sequences'];
        unset($args['trade_after']);
        unset($args['stop_loss_percent']);
        unset($args['sequences']);
        unset($args['sid']);

        $this->stock = $args['stock'];
        $this->filled_price = $this->stock['price'];
    }

    public function __construct(array $argv, string $which)
    {
        $this->which = $which;
        if ($which == 'buy')
            $this->close_position = "SELL";
        else
            $this->close_position = "BUYTOCOVER";

        $this->parseArgs($argv);

        $this->config = Config::getInst();
        $this->config->refresh();
        $this->bell = new Ndate($this->config['globals']['close_time']);
        $this->trade_station = new TradeStation(ucfirst($this->which));
    }

    public function run(): void
    {
        echo "Started Sequence for {$this->stock['symbol']} at " . (new Ndate)->format(Ndate::DATE_TIME) . "\n";

        //////////////////////////// Trading Sequence
        $is_filled = false;
        foreach ($this->stages as $i => $stage_config) {
            echo "\nStage $i: {$stage_config['percent']} limit\n";
            $this->stock['quantity'] = $this->trade_station->getExecQuantity($this->stock['order_id']);
            echo "Actual Executed Quantity: " . $this->stock['quantity'] . "\n";

            // Stage 0 is the base limit price and it is supposed to be already set with the order.
            if ($i > 0)
                $this->stock['limit_id'] = $this->trade_station->editOrder($this->stock, $this->stock['limit_id'], [
                    'order_type' => 'Limit',
                    'trade_action' => $this->close_position,
                    'percent' => $stage_config['percent']
                ]);


            // busy wait until limit is exceeded for all items or wait_time finishes
            echo "Waiting till limit filled or " . $stage_config['wait_time'] . " mins pass\n";
            $is_filled = $this->waitFilling($stage_config['wait_time'] * 60);

            if ($is_filled || $this->aboutToClose())
                break;
        }

        (new TradeMonitor)->remove($this->stock['symbol']);

        if (!$is_filled) {
            $this->stock['quantity'] = $this->trade_station->getExecQuantity($this->stock['order_id']);

            StockLogger::logStock(
                ucfirst($this->which),
                "Cancel OCO",
                [
                    ...$this->stock,
                    'OrderID' => -1,
                    'price' => '-'
                ]
            );

            $this->trade_station->closePosition([
                'order_id' => $this->stock['order_id'],
                'limit_id' => $this->stock['limit_id'],
                'stop_id' => $this->stock['stop_id']
            ]);
            
            echo "Failed; to reach any of the limits, Closing positions anyways\n";
            $this->status = self::TIMEOUT;
        } else {
            if ($is_filled[1]) {
                $loss_percent = $is_filled[3] / $this->filled_price - 1;
                echo "FilledPrice: {$is_filled[3]} with loss percentage $loss_percent \n";
            };

            StockLogger::logStock(
                ucfirst($this->which),
                "Limit " . $is_filled[0],
                [
                    ...$this->stock,
                    'OrderID' => -1,
                    'price' => $is_filled[2]
                ]
            );

            StockLogger::logStock(
                ucfirst($this->which),
                "StopLoss " . $is_filled[1],
                [
                    ...$this->stock,
                    'OrderID' => -1,
                    'price' => $is_filled[3]
                ]
            );

            $this->status = $is_filled[0] == 'FLL' ? self::FILLED : self::STOPLOSS;
        }
    }

    private function aboutToClose()
    {
        if ((new Ndate)->minutesUntil($this->bell) < CLOSING_TOLERANCE)
            echo "Approached Market End\n";

        return (new Ndate)->minutesUntil($this->bell) < CLOSING_TOLERANCE;
    }

    private function waitFilling(int $secs)
    {
        $start = time();
        while (time() - $start < $secs) {
            $limit_order = $this->trade_station->getOrder($this->stock['limit_id']);
            $stop_order = $this->trade_station->getOrder($this->stock['stop_id']);

            if (!$limit_order || !$stop_order) {
                echo "Limit order or stop order not found \n";
                var_dump($limit_order, $stop_order);
                sleep(15);
                continue;
            }

            $limit_status = $limit_order['Status'];
            $stop_status = $stop_order['Status'];

            echo "Limit: $limit_status \n";
            echo "StopLoss: $stop_status \n";

            if ($limit_status == 'FLL' || $stop_status == 'FLL')
                return [$limit_status, $stop_status, $limit_order['FilledPrice'] ?? null, $stop_order['FilledPrice'] ?? null];

            if (!in_array($limit_status, ['ACK', 'OPN'])) {
                echo "Weird Limit Order Status. Here is the order\n";
                prettyPrint($limit_order);
            }

            if (!in_array($stop_status, ['ACK', 'OPN'])) {
                echo "Weird Stoploss Order Status. Here is the order\n";
                prettyPrint($stop_order);
            }

            if ($this->aboutToClose() || $limit_status == 'OUT' && $stop_status == 'OUT')
                return false;

            sleep(20);
        }
        return false;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getStock(): array
    {
        return $this->stock;
    }
}
