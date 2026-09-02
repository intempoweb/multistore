<?php

return [
    'recipient' => env('ORDER_SALES_EXPORT_RECIPIENT', 'm.pazzaglia@intempo.it'),
    'schedule_day' => (int) env('ORDER_SALES_EXPORT_SCHEDULE_DAY', 1),
    'schedule_time' => env('ORDER_SALES_EXPORT_SCHEDULE_TIME', '08:00'),
    'timezone' => env('ORDER_SALES_EXPORT_TIMEZONE', 'Europe/Rome'),
];
