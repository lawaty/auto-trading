<?php

class Order extends Entity
{
    protected int $tradestation_order_id;
    protected int $day_id;
    protected int $quantity;
    protected string $symbol;
    protected float $price;
    protected string $execution_type;
    protected ?string $execution_details;
    

}
