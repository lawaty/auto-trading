<?php

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
    private float $danger;
    private string $close_position;
    private int $status = -1;

    private function parseArgs(array $args): void
    {
        $this->danger = $args['stop_loss_percent'];
        $this->stages = $args['sequences'];
        unset($args['trade_after']);
        unset($args['stop_loss_percent']);
        unset($args['sequences']);
        unset($args['sid']);

        $this->stock = $args['stock'];
    }

    public function __construct(array $argv, string $which)
    {
        $this->which = $which;
        if ($which == 'buy')
            $this->close_position = "SELL";
        else
            $this->close_position = "BUYTOCOVER";

        $this->parseArgs($argv);

        $this->config = new Config;
        $this->bell = new Ndate($this->config['globals']['until']);
        $this->trade_station = new TradeStation(ucfirst($this->which));
    }

    public function run(): void
    {
        //////////////////////////// Trading Sequence
        $is_filled = false;
        foreach ($this->stages as $i => $stage_config) {
            echo "\nStage $i: {$stage_config['percent']} limit\n";
            $this->stock['quantity'] = $this->trade_station->getExecQuantity($this->stock['order_id']);
            echo "Actual Executed Quantity: " . $this->stock['quantity'] . "\n";

            if (!isset($this->stock['stop_id'])) {
                [$this->stock['stop_id'], $this->stock['limit_id']] = $this->trade_station->placeOCO($this->stock, [[
                    'percent' => $this->danger,
                    'order_type' => 'StopMarket',
                    'trade_action' => $this->close_position
                ], [
                    'percent' => $stage_config['percent'],
                    'order_type' => 'Limit',
                    'trade_action' => $this->close_position
                ]]);
            } else if ($order_id = $this->trade_station->editOrder($this->stock, $this->stock['limit_id'], [
                'order_type' => 'Limit',
                'trade_action' => $this->close_position,
                'percent' => $stage_config['percent']
            ]))
                $this->stock['limit_id'] = $order_id;

            // busy wait until limit is exceeded for all items or wait_time finishes
            echo "Waiting till limit filled or " . $stage_config['wait_time'] . " mins pass\n";
            $is_filled = $this->waitFilling($stage_config['wait_time'] * 60);

            if ($is_filled || $this->aboutToClose())
                break;
        }

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

            $this->trade_station->cancel($this->stock['limit_id']);
            $this->trade_station->cancel($this->stock['stop_id']);
            $this->trade_station->placeOrder($this->stock, 'Market', $this->close_position);
            echo "Failed to reach any of the limits, Closing positions anyways\n";
            $this->status = self::TIMEOUT;
        } else {
            echo "FilledPrice: " . $is_filled[2] . "\n";

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
                    'price' => '-'
                ]
            );

            $this->status = $is_filled[0] == 'FLL' ? self::FILLED : self::STOPLOSS;
        }
    }

    private function aboutToClose()
    {
        return (new Ndate)->minutesUntil($this->bell) < CLOSING_TOLERANCE;
    }


    private function waitFilling(int $secs)
    {
        $start = time();
        while (time() - $start < $secs) {
            $limit_order = $this->trade_station->getOrder($this->stock['limit_id']);
            $stop_order = $this->trade_station->getOrder($this->stock['stop_id']);
            $limit_status = $limit_order['Status'];
            $stop_status = $stop_order['Status'];

            echo "Limit: $limit_status \n";
            echo "StopLoss: $stop_status \n";

            if ($stop_status == 'REJ')
                echo "Stoploss rejected because: {$stop_order['RejectReason']}\n";

            /**
             * @todo handle limit_order rejection
             */

            if ($limit_status == 'FLL' || $stop_status == 'FLL')
                return [$limit_status, $stop_status, $limit_order['FilledPrice'] ?? -1];

            if ($limit_status != 'ACK') {
                echo "Weird Limit Order Status. Here is the order\n";
                prettyPrint($limit_order);
            }

            if ($stop_status != 'ACK') {
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
