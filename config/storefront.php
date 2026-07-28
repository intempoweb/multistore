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

    'store_locator' => [
        'excluded_customers' => [
            ['ditta_cg18' => 1, 'tipocf_cg44' => 0, 'clifor_cg44' => 31174, 'label' => 'TIZIANO DI IGINO GENOVA E C. SAS TIPOG'],
            ['ditta_cg18' => 1, 'tipocf_cg44' => 0, 'clifor_cg44' => 34070, 'label' => 'FIPELL SERVICE S.R.L.'],
            ['ditta_cg18' => 1, 'tipocf_cg44' => 0, 'clifor_cg44' => 100000, 'label' => 'PROVA - NON SPEDIRE - NON FATTURARE'],
            ['ditta_cg18' => 1, 'tipocf_cg44' => 0, 'clifor_cg44' => 100277, 'label' => 'PERUZZI MARCO'],
        ],
    ],
];
