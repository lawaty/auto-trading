<?php

class UnwantedStockMapper extends Mapper
{
    public static string $table = 'unwanted_stocks';
    public static array $required = [
        "symbol",
        "type",
    ];
}
