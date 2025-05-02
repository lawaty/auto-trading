<?php

class Parser
{
    private static string $html_url = "https://www.members.stockmonitor.com/signal/";
    public function __construct()
    {
    }

    public static function getDataFromTable($table_html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($table_html);
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($dom);
        $data = [];

        $query = '//div[@class="table-responsive table-wrapper"]/div/table[@class="table table-ellipsis"]/tbody/tr';

        $result = $xpath->query($query);

        foreach ($result as $node) {
            $data[] = static::getDetails($node);
        }

        return $data;
    }
    public static function getRunId($filter_id, $cookie)
    {
        $html = ArteCurl::request("GET", static::$html_url, ['sid' => $filter_id], ["Cookie" => $cookie]);
        preg_match("/test-signal-[0-9]+/", $html, $matches);
        return $matches[0];
    }
    private static function getDetails($tr)
    {
        $details = array();

        $tdElements = $tr->getElementsByTagName('td');

        if ($tdElements->length >= 10) {
            $details['symbol'] = trim($tdElements->item(1)->nodeValue);
            $details['name'] = trim($tdElements->item(2)->nodeValue);
            $details['price'] = trim($tdElements->item(3)->getElementsByTagName('span')->item(0)->nodeValue);
            $details['change'] = trim($tdElements->item(4)->nodeValue);
            $details['open'] = trim($tdElements->item(5)->nodeValue);
            $details['high'] = trim($tdElements->item(6)->nodeValue);
            $details['volume'] = trim($tdElements->item(7)->nodeValue);
            $details['market_cap'] = trim($tdElements->item(8)->nodeValue);
        }

        return $details;
    }
}
