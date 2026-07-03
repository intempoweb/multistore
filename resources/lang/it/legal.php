<?php

return [

    'company' => [
        'company' => 'Società',
        'address' => 'Indirizzo',
        'vat' => 'Partita IVA',
        'tax_code' => 'Codice fiscale',
        'email' => 'Email',
        'pec' => 'PEC',
        'phone' => 'Telefono',
        'website' => 'Sito web',
    ],

    'common' => [
        'always_active' => 'Sempre attivi',
        'consent_required' => 'Consenso richiesto',
        'technical_cookies' => 'Cookie tecnici',
    ],

    'privacy' => [

        'title' => 'Informativa Privacy',
        'last_updated' => 'Ultimo aggiornamento',

        'owner' => [
            'title' => 'Titolare del trattamento',
            'text' => 'Il titolare del trattamento dei dati personali è la società indicata di seguito.',
        ],

        'data_collected' => [
            'title' => 'Dati raccolti',
            'text' => 'Durante l’utilizzo del sito e dei servizi di e-commerce possono essere raccolte le seguenti categorie di dati:',
            'text_b2b' => 'Durante l’utilizzo del portale B2B possono essere raccolte le seguenti categorie di dati riferite all’account aziendale, agli utenti abilitati e agli ordini.',
            'text_b2c' => 'Durante l’utilizzo dello shop online possono essere raccolte le seguenti categorie di dati necessarie per account, checkout e acquisti.',
            'account' => 'Dati identificativi e di registrazione dell’account.',
            'orders' => 'Dati relativi agli ordini, indirizzi di spedizione e fatturazione.',
            'payments' => 'Informazioni necessarie per la gestione dei pagamenti (i dati della carta sono gestiti direttamente dal provider di pagamento).',
            'support' => 'Comunicazioni inviate al servizio clienti.',
            'technical' => 'Dati tecnici, indirizzo IP, log di sicurezza e informazioni sul dispositivo.',
            'b2b_commercial' => 'Informazioni commerciali collegate al cliente B2B, listini, condizioni, agenti e visibilità catalogo.',
        ],

        'purposes' => [
            'title' => 'Finalità del trattamento',
            'text' => 'I dati personali vengono trattati per le seguenti finalità:',
            'text_b2b' => 'I dati vengono trattati per gestire il rapporto commerciale, gli ordini B2B e i servizi collegati all’area riservata.',
            'text_b2c' => 'I dati vengono trattati per consentire navigazione, acquisto, pagamento e assistenza post-vendita.',
            'ecommerce' => 'Gestione degli ordini e vendita dei prodotti.',
            'customer_area' => 'Accesso all’area riservata e gestione dell’account.',
            'shipping' => 'Preparazione e spedizione degli ordini.',
            'invoicing' => 'Adempimenti fiscali, amministrativi e contabili.',
            'security' => 'Prevenzione di frodi e tutela della sicurezza del servizio.',
            'marketing_optional' => 'Invio di comunicazioni commerciali previo consenso, ove richiesto.',
            'analytics_optional' => 'Analisi statistiche e miglioramento del sito.',
            'b2b_commercial' => 'Gestione di listini, condizioni commerciali, documenti e supporto da parte degli operatori autorizzati.',
        ],

        'legal_basis' => [
            'title' => 'Base giuridica',
            'text' => 'Il trattamento viene effettuato sulla base delle seguenti condizioni previste dal GDPR:',
            'contract' => 'Esecuzione del contratto di vendita.',
            'legal_obligation' => 'Adempimento di obblighi di legge.',
            'legitimate_interest' => 'Legittimo interesse del titolare alla sicurezza e al miglioramento dei servizi.',
            'consent' => 'Consenso dell’interessato nei casi previsti.',
        ],

        'services' => [
            'title' => 'Servizi utilizzati',
            'text' => 'Per l’erogazione dei servizi possono essere utilizzati fornitori terzi.',

            'table' => [
                'service' => 'Servizio',
                'purpose' => 'Finalità',
            ],

            'ecommerce_platform' => 'Piattaforma e-commerce',
            'ecommerce_platform_description' => 'Gestione del catalogo, ordini, clienti e funzionalità del sito.',

            'payment_description' => 'Gestione sicura dei pagamenti elettronici.',
            'shipping_description' => 'Gestione delle spedizioni e della logistica.',
            'analytics_description' => 'Analisi statistiche anonime o aggregate sul traffico del sito.',
            'ads_description' => 'Misurazione delle conversioni e campagne pubblicitarie.',
            'maps_description' => 'Visualizzazione di mappe interattive.',
            'instagram_description' => 'Visualizzazione del feed Instagram aziendale.',
            'b2b_area_description' => 'Gestione dell’area cliente B2B, documenti, listini, ordini e funzioni operative riservate.',
        ],

        'retention' => [
            'title' => 'Conservazione dei dati',
            'text' => 'I dati vengono conservati per il tempo necessario all’esecuzione del contratto, agli obblighi di legge e alla tutela dei diritti del titolare.',
        ],

        'rights' => [
            'title' => 'Diritti dell’interessato',
            'text' => 'L’interessato può esercitare in qualsiasi momento i diritti previsti dagli articoli 15-22 del GDPR.',
            'access' => 'Accesso ai dati.',
            'rectification' => 'Rettifica dei dati.',
            'erasure' => 'Cancellazione dei dati.',
            'restriction' => 'Limitazione del trattamento.',
            'portability' => 'Portabilità dei dati.',
            'objection' => 'Opposizione al trattamento.',
            'withdraw' => 'Revoca del consenso, ove applicabile.',
        ],

        'contact' => [
            'title' => 'Contatti',
            'text' => 'Per qualsiasi richiesta relativa al trattamento dei dati personali è possibile contattare il titolare ai recapiti indicati in questa pagina.',
        ],
    ],

    'shipping_returns' => [
        'title' => 'Spedizioni e resi',
        'last_updated' => 'Ultimo aggiornamento',
        'intro_b2c' => 'In questa pagina trovi le informazioni principali su spedizioni, consegne e resi per gli acquisti effettuati come cliente finale.',
        'intro_b2b' => 'In questa pagina trovi le informazioni principali su spedizioni, consegne e richieste di reso per gli ordini B2B.',

        'shipping' => [
            'title' => 'Spedizioni',
            'b2c_text' => 'Costi, tempi e disponibilità della spedizione vengono calcolati durante il checkout in base a destinazione, prodotti e regole attive dello store.',
            'b2b_text' => 'Le spedizioni B2B vengono gestite secondo le condizioni commerciali associate al cliente, agli indirizzi abilitati e alle regole operative dello store.',
            'tracking' => 'Quando disponibile, il tracking viene mostrato nell’area personale e nelle comunicazioni relative all’ordine.',
        ],

        'returns' => [
            'title' => 'Resi',
            'b2c_text' => 'Per gli acquisti B2C il cliente può richiedere il reso entro :days giorni dalla consegna, salvo prodotti esclusi per legge o personalizzati.',
            'b2c_order_locked' => 'Una volta completato e confermato, l’ordine non può essere annullato o modificato.',
            'b2c_request' => 'In relazione a quanto prescritto dalla Direttiva n. 2000/31/CE e dal D.Lgs. n. 70/2003, il cliente ha il diritto di richiedere la sostituzione o il reso della merce entro :days giorni a decorrere dalla data di consegna, inviandone comunicazione a :email. Le richieste pervenute dopo la scadenza di questo termine potranno essere rifiutate dal venditore.',
            'b2c_shipping' => 'La merce restituita dovrà essere inviata tramite un pacco assicurato, completo di numero di identificazione, al deposito indicato: :deposit. Le spese di spedizione sono a carico del mittente.',
            'b2c_instructions' => 'Le coordinate e le istruzioni complete per la sostituzione o il rimborso verranno inviate per posta elettronica al cliente.',
            'b2c_refund' => 'Una volta giunta al deposito e controllate le condizioni della merce, verrà emesso il rimborso, automaticamente accreditato mediante lo stesso mezzo di pagamento utilizzato per l’acquisto e confermato via email all’indirizzo riportato sul relativo ordine.',
            'b2c_rejection' => 'Non potranno essere accolte richieste di restituzione e/o rimborso per prodotti ricevuti dal deposito in condizioni diverse da quelle iniziali: la merce dovrà trovarsi nelle medesime condizioni in cui è stata consegnata al destinatario.',
            'b2c_damaged' => 'In caso di prodotti difettosi e/o danneggiati, :company, proprietaria del sito :site, si assume la piena responsabilità. In tal caso, sarà necessario contattare via email il servizio clienti (:email) indicando il numero d’ordine e allegando una foto del prodotto difettoso o danneggiato al fine di ottenere la sostituzione o il rimborso.',
            'b2c_retailers' => 'Le sostituzioni e i resi della merce acquistata su :site dovranno essere esclusivamente comunicati al servizio clienti e non potranno avvenire presso rivenditori terzi.',
            'b2b_text' => 'Per gli ordini B2B eventuali resi o contestazioni devono essere concordati con il servizio clienti o con il referente commerciale secondo le condizioni applicate al cliente.',
            'condition' => 'I prodotti devono essere integri, completi di imballo originale e non utilizzati oltre quanto necessario per verificarne natura e caratteristiche.',
        ],

        'how_to_request' => [
            'title' => 'Come richiedere assistenza',
            'text' => 'Per richieste su spedizioni, consegne o resi puoi contattarci indicando numero ordine, prodotto interessato e motivo della richiesta.',
        ],
    ],

    'cookie_banner' => [
        'text' => 'Usiamo i cookie tecnici necessari e, con il tuo consenso, anche servizi esterni per migliorare la tua esperienza sulla nostra piattaforma.',
        'privacy' => 'Privacy',
        'cookies' => 'Cookie policy',
        'accept' => 'Accetto',
        'aria_label' => 'Informativa cookie',
    ],

    'cookies' => [

        'title' => 'Cookie Policy',
        'last_updated' => 'Ultimo aggiornamento',

        'intro' => 'Questo sito utilizza cookie tecnici e, previo consenso ove necessario, cookie analitici e di marketing.',

        'types' => [
            'title' => 'Tipologie di cookie',
            'technical' => 'Cookie tecnici indispensabili al funzionamento del sito.',
            'analytics' => 'Cookie analitici per statistiche e miglioramento del servizio.',
            'marketing' => 'Cookie pubblicitari e di profilazione.',
            'third_party' => 'Cookie installati da servizi di terze parti.',
        ],

        'services' => [
            'title' => 'Servizi che possono installare cookie',

            'table' => [
                'service' => 'Servizio',
                'purpose' => 'Finalità',
            ],

            'google_analytics' => 'Google Analytics',
            'google_ads' => 'Google Ads',
            'google_maps' => 'Google Maps',
            'instagram' => 'Instagram',
        ],

        'management' => [
            'title' => 'Gestione dei cookie',
            'text' => 'Le preferenze possono essere modificate tramite il banner cookie o attraverso le impostazioni del browser.',
        ],
    ],

];
