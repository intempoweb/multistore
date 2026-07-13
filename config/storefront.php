<?php

return [
    'checkout' => [
        'defer_erp_export_item_threshold' => (int) env('STOREFRONT_CHECKOUT_DEFER_ERP_EXPORT_ITEM_THRESHOLD', 500),
        'queue_mail_item_threshold' => (int) env('STOREFRONT_CHECKOUT_QUEUE_MAIL_ITEM_THRESHOLD', 30),
        'mail_items_display_limit' => (int) env('STOREFRONT_CHECKOUT_MAIL_ITEMS_DISPLAY_LIMIT', 80),
        'product_images_archive_item_limit' => (int) env('STOREFRONT_CHECKOUT_PRODUCT_IMAGES_ARCHIVE_ITEM_LIMIT', 120),
        'success_items_display_limit' => (int) env('STOREFRONT_CHECKOUT_SUCCESS_ITEMS_DISPLAY_LIMIT', 20),
        'account_order_items_display_limit' => (int) env('STOREFRONT_ACCOUNT_ORDER_ITEMS_DISPLAY_LIMIT', 80),
    ],
];
