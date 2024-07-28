<?php
class Sequence_old
{
    private $starting_date;
    public $params;
    public StockMonitor $stockMonitor;
    private $stocks_number;
    public function __construct($which)
    {
        $json_data = file_get_contents(JSONS_DIR . "/params.json");
        $this->params = json_decode($json_data, true);
        $this->stockMonitor = new StockMonitor();
        $this->stocks_number = $this->params[$which]['number_of_trades'];
    }
    public static function getCurrentDay()
    {
        $date = (new DateTime((new Ndate)->format(Ndate::DATE)));

        $dayName = $date->format('l');
        return $dayName;
    }
    public static function getCurrentDate()
    {
        return (new DateTime((new Ndate)->format(Ndate::DATE)));
    }
    public static function getTime()
    {
        return (new Ndate)->format(Ndate::TIME);
    }
    public function isNewDay()
    {
        // Get the current date
        $currentDate = (new DateTime((new Ndate)->format(Ndate::DATE)));
        $day = DayMapper::get(['date' => $currentDate->format('d-m-Y')]);
        if (!$day) {
            DayMapper::create(['name' => $currentDate->format('l'), 'date' => $currentDate->format('d-m-Y')]);
            return true;
        }

        return false;
    }
    public function isClosedDay()
    {
        if (in_array(static::getCurrentDay(), $this->params['globals']['closed_days']))
            return true;
        else
            return false;
    }
    public function buyFilter()
    {
        $this->stockMonitor->initFilter(StockMonitor::BUY);
        $stocks = $this->stockMonitor->getStocks(StockMonitor::BUY);
        while (!$stocks) {
            $this->stockMonitor->initFilter(StockMonitor::BUY);
            try {
                $stocks = $this->stockMonitor->getStocks(StockMonitor::BUY);
            } catch (Exception | Error $e) {
                $this->stockMonitor->initFilter(StockMonitor::BUY);
            }
        }
        return array_slice($stocks, 0, $this->stocks_number);
    }
    public function shortFilter()
    {
        $stocks = $this->stockMonitor->getStocks(StockMonitor::SELL);

        while (!$stocks) {
            $this->stockMonitor->initFilter(StockMonitor::SELL);
            try {
                $stocks = $this->stockMonitor->getStocks(StockMonitor::SELL);
            } catch (Exception | Error $e) {
                $this->stockMonitor->initFilter(StockMonitor::SELL);
            }
        }
        return array_slice($stocks, 0, $this->stocks_number);
    }
    public function setBuyTestSignals()
    {
        $this->stockMonitor->initFilter(StockMonitor::BUY);
    }
    public function setShortTestSignals()
    {
        $this->stockMonitor->initFilter(StockMonitor::SELL);
    }
}
