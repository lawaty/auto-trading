<?php

class TradedStockMapper extends Mapper
{
    public static string $table = 'traded_stocks';
    public static array $required = [
        "day_id",
        "symbol",
        "pchange",
        "price",
    ];
}
