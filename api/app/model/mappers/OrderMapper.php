<?php

class OrderMapper extends Mapper
{
    public static string $table = 'orders';
    public static array $required = [
        "tradestation_order_id",
        "day_id",
        "symbol",
        "price",
        "quantity",
        "execution_type"
    ];
    public static array $optional = ['execution_details'];
}
