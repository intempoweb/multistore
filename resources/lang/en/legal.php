<?php

return [

    'company' => [
        'company' => 'Company',
        'address' => 'Address',
        'vat' => 'VAT number',
        'tax_code' => 'Tax code',
        'email' => 'Email',
        'pec' => 'Certified email',
        'phone' => 'Phone',
        'website' => 'Website',
    ],

    'common' => [
        'always_active' => 'Always active',
        'consent_required' => 'Consent required',
        'technical_cookies' => 'Technical cookies',
    ],

    'privacy' => [

        'title' => 'Privacy Policy',
        'last_updated' => 'Last updated',

        'owner' => [
            'title' => 'Data controller',
            'text' => 'The data controller for personal data is the company listed below.',
        ],

        'data_collected' => [
            'title' => 'Data collected',
            'text' => 'When using the website and e-commerce services, the following categories of data may be collected:',
            'text_b2b' => 'When using the B2B portal, the following categories of data may be collected in relation to the business account, enabled users and orders.',
            'text_b2c' => 'When using the online shop, the following categories of data may be collected to manage accounts, checkout and purchases.',
            'account' => 'Identification and account registration data.',
            'orders' => 'Order data, shipping addresses and billing addresses.',
            'payments' => 'Information required to manage payments. Card details are handled directly by the payment provider.',
            'support' => 'Communications sent to customer support.',
            'technical' => 'Technical data, IP address, security logs and device information.',
            'b2b_commercial' => 'Commercial information linked to the B2B customer, price lists, terms, agents and catalog visibility.',
        ],

        'purposes' => [
            'title' => 'Purposes of processing',
            'text' => 'Personal data is processed for the following purposes:',
            'text_b2b' => 'Data is processed to manage the commercial relationship, B2B orders and services connected to the reserved area.',
            'text_b2c' => 'Data is processed to allow browsing, purchasing, payment and after-sales support.',
            'ecommerce' => 'Managing orders and selling products.',
            'customer_area' => 'Accessing the reserved area and managing the account.',
            'shipping' => 'Preparing and shipping orders.',
            'invoicing' => 'Tax, administrative and accounting obligations.',
            'security' => 'Fraud prevention and protection of service security.',
            'marketing_optional' => 'Sending commercial communications with prior consent, where required.',
            'analytics_optional' => 'Statistical analysis and improvement of the website.',
            'b2b_commercial' => 'Management of price lists, commercial terms, documents and support by authorized operators.',
        ],

        'legal_basis' => [
            'title' => 'Legal basis',
            'text' => 'Processing is carried out on the basis of the following conditions provided by the GDPR:',
            'contract' => 'Performance of the sales contract.',
            'legal_obligation' => 'Compliance with legal obligations.',
            'legitimate_interest' => 'The controller’s legitimate interest in security and service improvement.',
            'consent' => 'Consent of the data subject where applicable.',
        ],

        'services' => [
            'title' => 'Services used',
            'text' => 'Third-party providers may be used to deliver the services.',

            'table' => [
                'service' => 'Service',
                'purpose' => 'Purpose',
            ],

            'ecommerce_platform' => 'E-commerce platform',
            'ecommerce_platform_description' => 'Management of catalog, orders, customers and website features.',

            'payment_description' => 'Secure management of electronic payments.',
            'shipping_description' => 'Management of shipments and logistics.',
            'analytics_description' => 'Anonymous or aggregated statistical analysis of website traffic.',
            'ads_description' => 'Conversion measurement and advertising campaigns.',
            'maps_description' => 'Display of interactive maps.',
            'instagram_description' => 'Display of the company Instagram feed.',
            'b2b_area_description' => 'Management of the B2B customer area, documents, price lists, orders and reserved operational features.',
        ],

        'retention' => [
            'title' => 'Data retention',
            'text' => 'Data is retained for the time necessary to perform the contract, comply with legal obligations and protect the controller’s rights.',
        ],

        'rights' => [
            'title' => 'Data subject rights',
            'text' => 'The data subject may exercise the rights provided by Articles 15-22 of the GDPR at any time.',
            'access' => 'Access to data.',
            'rectification' => 'Rectification of data.',
            'erasure' => 'Erasure of data.',
            'restriction' => 'Restriction of processing.',
            'portability' => 'Data portability.',
            'objection' => 'Objection to processing.',
            'withdraw' => 'Withdrawal of consent, where applicable.',
        ],

        'contact' => [
            'title' => 'Contact',
            'text' => 'For any request regarding the processing of personal data, the controller can be contacted using the details shown on this page.',
        ],
    ],

    'shipping_returns' => [
        'title' => 'Shipping and returns',
        'last_updated' => 'Last updated',
        'intro_b2c' => 'Here you can find the main information about shipping, delivery and returns for purchases made as an end customer.',
        'intro_b2b' => 'Here you can find the main information about shipping, delivery and return requests for B2B orders.',

        'shipping' => [
            'title' => 'Shipping',
            'b2c_text' => 'Shipping costs, times and availability are calculated during checkout based on destination, products and active store rules.',
            'b2b_text' => 'B2B shipments are managed according to the commercial terms linked to the customer, enabled addresses and store operating rules.',
            'tracking' => 'When available, tracking is shown in the personal area and in order communications.',
        ],

        'returns' => [
            'title' => 'Returns',
            'b2c_text' => 'For B2C purchases, the customer may request a return within :days days from delivery, except for products excluded by law or customized items.',
            'b2c_order_locked' => 'Once completed and confirmed, the order cannot be cancelled or modified.',
            'b2c_request' => 'In accordance with Directive no. 2000/31/EC and Italian Legislative Decree no. 70/2003, the customer has the right to request replacement or return of the goods within :days days from the delivery date by sending notice to :email. Requests received after this deadline may be refused by the seller.',
            'b2c_shipping' => 'Returned goods must be sent by insured parcel, complete with identification number, to the indicated warehouse: :deposit. Return shipping costs are borne by the sender.',
            'b2c_instructions' => 'The complete details and instructions for replacement or refund will be sent to the customer by email.',
            'b2c_refund' => 'Once the goods have arrived at the warehouse and their condition has been checked, the refund will be issued using the same payment method used for the purchase and confirmed by email to the address shown on the order.',
            'b2c_rejection' => 'Return and/or refund requests cannot be accepted for products received by the warehouse in a condition other than the original one: the goods must be in the same condition in which they were delivered to the recipient.',
            'b2c_damaged' => 'In case of defective and/or damaged products, :company, owner of the website :site, assumes full responsibility. In this case, the customer must contact customer service by email (:email), indicating the order number and attaching a photo of the defective or damaged product in order to obtain replacement or refund.',
            'b2c_retailers' => 'Replacements and returns for goods purchased on :site must be communicated exclusively to customer service and cannot be handled through third-party retailers.',
            'b2b_text' => 'For B2B orders, returns or claims must be agreed with customer service or the commercial contact according to the terms applied to the customer.',
            'condition' => 'Products must be intact, complete with original packaging and not used beyond what is necessary to verify their nature and characteristics.',
        ],

        'how_to_request' => [
            'title' => 'How to request assistance',
            'text' => 'For shipping, delivery or return requests, please contact us with the order number, the relevant product and the reason for the request.',
        ],
    ],

    'cookie_banner' => [
        'text' => 'We use necessary technical cookies and, only when configured and accepted, external services to improve the experience.',
        'privacy' => 'Privacy',
        'cookies' => 'Cookie policy',
        'accept' => 'Accept',
        'accept_all' => 'Accept all',
        'necessary_only' => 'Necessary only',
        'customize' => 'Manage',
        'save' => 'Save preferences',
        'preferences_title' => 'Cookie preferences',
        'aria_label' => 'Cookie notice',
    ],

    'cookies' => [

        'title' => 'Cookie Policy',
        'last_updated' => 'Last updated',

        'intro' => 'This website uses technical cookies and, with consent where required, analytics and marketing cookies.',

        'current_preferences' => 'Current preferences',
        'installed_title' => 'Cookies detected in your browser',
        'installed_text' => 'This list shows cookies readable from this page. Some protected technical cookies, such as HttpOnly session cookies, cannot be read by the browser but are listed in the table.',
        'installed_empty' => 'No readable cookies found for this domain.',

        'types' => [
            'title' => 'Types of cookies',
            'technical' => 'Technical cookies required for the website to work.',
            'analytics' => 'Analytics cookies for statistics and service improvement.',
            'marketing' => 'Advertising and profiling cookies.',
            'third_party' => 'Cookies installed by third-party services.',
        ],

        'categories' => [
            'necessary' => [
                'label' => 'Necessary',
                'description' => 'Used for login, security, cart, session and saving cookie preferences.',
            ],
            'analytics' => [
                'label' => 'Analytics',
                'description' => 'Help us understand how the website is used and improve it.',
            ],
            'marketing' => [
                'label' => 'Marketing',
                'description' => 'Measure campaigns and advertising content.',
            ],
            'third_party' => [
                'label' => 'External services',
                'description' => 'Enable content or features provided by third-party platforms.',
            ],
        ],

        'services' => [
            'title' => 'Services that may install cookies',

            'table' => [
                'category' => 'Category',
                'service' => 'Service',
                'provider' => 'Provider',
                'cookies' => 'Cookies',
                'duration' => 'Duration',
                'purpose' => 'Purpose',
            ],

            'platform' => 'Ecommerce platform',
            'google_analytics' => 'Google Analytics',
            'google_ads' => 'Google Ads',
            'google_maps' => 'Google Maps',
            'instagram' => 'Instagram',
        ],

        'purposes' => [
            'technical' => 'Website operation, user session, security, cart and cookie preferences.',
            'analytics' => 'Aggregated statistics about website use and experience improvement.',
            'marketing' => 'Advertising campaign and conversion measurement.',
            'maps' => 'Map display and geolocation features on pages that use them.',
            'instagram' => 'Instagram feed display and social content links.',
            'security' => 'Protection of forms from spam and abuse.',
            'payment' => 'Secure online payment handling.',
        ],

        'durations' => [
            'session_or_configured' => 'Session or configured duration',
            'google_analytics' => 'From session up to 24 months',
            'google_ads' => 'Up to 90 days or according to Google settings',
            'third_party' => 'According to provider settings',
            'stripe' => 'Up to 1 year',
        ],

        'management' => [
            'title' => 'Cookie management',
            'text' => 'You can change your preferences from this page at any time. Necessary technical cookies remain active because they are required for the website to work.',
            'saved' => 'Cookie preferences saved.',
        ],
    ],

];
