<?php

use App\Models\Store;

if (!function_exists('current_store')) {
    function current_store(): ?Store
    {
        return app()->bound('currentStore')
            ? app('currentStore')
            : null;
    }
}