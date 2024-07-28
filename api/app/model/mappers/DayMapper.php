<?php

class DayMapper extends Mapper
{
    public static string $table = 'days';
    public static array $required = [
        "name",
        "date",
    ];
}
