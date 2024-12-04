<?php

const CLOSING_TOLERANCE = 30; // mins

class Sequence
{
    const INACTIVE = -1;
    const RUNNING = 0;
    const TRAILSTOP = 1;
    const STOPLOSS = 2;
    const TIMEOUT = 3;
    const FAILED = 4;

    private string $which;
    private string $close_position;
    private array $stock;
    private Config $config;
    private ITradeStation $trade_station;
    private TradeMonitor $trade_monitor;

    private Ndate $bell;
    private array $stages;
    private int $current_stage = -1;
    private ?float $sold_price = null;

    private int $status = self::INACTIVE;

    private function parseArgs(array $args): void
    {
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
        $this->close_position = $which == 'buy' ? 'SELL' : 'BUYTOCOVER';
        $this->parseArgs($argv);

        $this->config = Config::getInst();
        $this->config->refresh();
        $this->bell = new Ndate($this->config['globals']['close_time']);
        $this->trade_station = new TradeStation($this->which);
        $this->trade_monitor = new TradeMonitor;
    }

    public function run(): void
    {
        $this->status = self::RUNNING;
        echo "Started Sequence for {$this->stock['symbol']} at " . (new Ndate)->format(Ndate::DATE_TIME) . "\n";

        //////////////////////////// Trading Sequence
        $filled_price = $this->stock['price'];

        $streamer = $this->trade_station->getStreamer($this->stock['symbol']);

        $streamer->setStreamCallback(function ($details) use ($filled_price) {
            static $meaningful_data_inst = -1;
            if (!isset($details['Bid']) && !isset($details['Last']))
                return true;

            $current_price = $details['Bid'] ?? $details['Last'];

            if ($meaningful_data_inst != -1) {
                $latency = microtime(true) - $meaningful_data_inst;
                $meaningful_data_inst = microtime(true);
            } else {
                $latency = 0;
                $meaningful_data_inst = microtime(true);
            }

            $percent_increase = ($current_price - $filled_price) / $filled_price;
            echo "Stock Increase: $percent_increase after $latency secs\n";

            if ($this->stoplossTriggered()) {
                echo "Stoploss Triggered with price {$this->sold_price}. Leaving...\n";
                $this->status = self::STOPLOSS;
                return false;
            }

            if ($this->aboutToClose()) {
                echo "Market about to close. Selling with the current profit whatever it was. \n";
                $this->trade_station->closePosition($this->trade_monitor->get($this->stock['symbol']));
                $this->trade_monitor->remove($this->stock['symbol']);
                $this->status = self::TIMEOUT;
                return false;
            }

            $stage_i = $this->getStageIndex($percent_increase);
            if ($stage_i > $this->current_stage) {
                echo "Moved to Stage $stage_i with trailing at {$this->stages[$stage_i]['trail']}\n";
                $trail_price = $filled_price * (1 + $this->stages[$stage_i]['trail']);
                try {
                    $this->trade_station->editOrder($this->stock, $this->stock['stop_id'], [
                        'percent' => $this->stages[$stage_i]['trail'],
                        'TradeAction' => $this->close_position,
                        'OrderType' => 'StopMarket'
                    ]);
                    echo "Stoploss is set to {$trail_price}\n";

                    $this->current_stage = $stage_i;
                } catch (OrderFailed $e) {
                    echo "Couldn't Replace Order StopPrice: " . trace($e) . "\n";
                }
            }

            return true;
        });

        echo "Starting trade stream. No trailstop is set currently\n";
        try {
            $streamer->stream();
        } catch (StreamRejected $e) {
            echo "Tradestation stream has been unexpectedly closed. \n";
            $this->trade_station->closePosition($this->trade_monitor->get($this->stock['symbol']));
            $this->status = self::FAILED;
        } finally {
            $this->trade_monitor->remove($this->stock['symbol']);
        }
    }

    private function stoplossTriggered()
    {
        static $lastFetchTime = 0;

        $currentTime = microtime(true);
        if ($currentTime - $lastFetchTime < 4)
            return false;

        $lastFetchTime = $currentTime;

        if (!isset($this->stock['stop_id']))
            return false;

        $order = $this->trade_station->getOrder($this->stock['stop_id']);
        $status = $order['Status'];

        if (!in_array($status, ['FLL', 'OUT', 'ACK', 'OPN'])) {
            echo "Weird Stoploss Order Status. Here is the order\n";
            prettyPrint($order);
        }

        if ($status == 'FLL')
            $this->sold_price = $order['FilledPrice'];
        return $status == 'FLL';
    }

    private function getStageIndex(float $percent_increase): ?int
    {
        $i = -1;
        while (
            isset($this->stages[$i + 1]) &&
            (
                $this->which == 'buy' && $this->stages[$i + 1]['trigger'] < $percent_increase ||
                $this->which == 'short' && $this->stages[$i + 1]['trigger'] > $percent_increase
            )
        )
            $i++;

        return $i;
    }

    private function aboutToClose()
    {
        $about_to_close = (new Ndate)->minutesUntil($this->bell) < CLOSING_TOLERANCE;
        echo "Leaving Market in " . (new Ndate)->minutesUntil($this->bell) - CLOSING_TOLERANCE . " mins\n";
        if ($about_to_close)
            echo "Approached Market End\n";

        return $about_to_close;
    }

    public function getStock(): array
    {
        return $this->stock;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
