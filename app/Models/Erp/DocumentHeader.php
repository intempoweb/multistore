<?php

namespace App\Models\Erp;

use App\Services\Storefront\Documents\DocumentGoodsDestinationResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Throwable;

class DocumentHeader extends Model
{
    protected $connection = 'erp';

    protected $table = 'DOCTESTATABASE_DO11';

    protected $primaryKey = 'NUMREG_CO99';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    private const HEADER_TABLE = 'DOCTESTATABASE_DO11';

    private const ORDER_VIEW = 'dbo.TESTATAVWEBDO30_TOT';

    private const ORDER_VIEW_ALIAS = 'WEB_ORDER';

    private const CUSTOMER_TABLE = 'dbo.ANAGRCLI_TOT';

    private const CUSTOMER_TABLE_ALIAS = 'ERP_CUSTOMER';

    private const DOCUMENT_REFERENCES_TABLE = 'dbo.DETTRIGHECORPORIF_TOT';

    public const STORE_LOCATOR_DOCUMENT_TYPES = [
        'DDT',
        'DDT RESO',
        'DDT_VARI',
        'FATT DDT',
        'FATTURA',
        'FATTURA RIEP',
        'ORDINE',
    ];

    /**
     * Colonne utilizzate nell'elenco documenti.
     *
     * Le colonne della testata sono qualificate perché la query può contenere
     * il LEFT JOIN verso TESTATAVWEBDO30_TOT.
     */
    public const INDEX_COLUMNS = [
        'DOCTESTATABASE_DO11.NUMREG_CO99',
        'DOCTESTATABASE_DO11.DITTA_CG18',
        'DOCTESTATABASE_DO11.CLIFOR_CG44',
        'DOCTESTATABASE_DO11.DATADOC_DO11',
        'DOCTESTATABASE_DO11.NUMSEZDOC_DO11',
        'DOCTESTATABASE_DO11.TIPODOCDECOD_MG36',
        'WEB_ORDER.PROVENORD',
    ];

    private const ERP_DATE_EXPRESSION =
        'TRY_CONVERT(datetime, DOCTESTATABASE_DO11.DATADOC_DO11, 103)';

    public function rows(): HasMany
    {
        return $this->hasMany(
            DocumentRow::class,
            'NUMREG_CO99',
            'NUMREG_CO99'
        )->orderBy('PROGRIGA_DO30');
    }

    /**
     * Aggiunge la provenienza degli ordini recuperandola dalla vista
     * TESTATAVWEBDO30_TOT.
     *
     * Il LEFT JOIN mantiene anche DDT, fatture e ordini che non sono
     * presenti nella vista web. In questi casi PROVENORD sarà null.
     */
    public function scopeWithOrderProvenance(Builder $query): Builder
    {
        return $query->leftJoin(
            self::ORDER_VIEW . ' as ' . self::ORDER_VIEW_ALIAS,
            function (JoinClause $join) {
                $join
                    ->on(
                        self::ORDER_VIEW_ALIAS . '.NUMREG_CO99',
                        '=',
                        self::HEADER_TABLE . '.NUMREG_CO99'
                    )
                    ->on(
                        self::ORDER_VIEW_ALIAS . '.DITTA_CG18',
                        '=',
                        self::HEADER_TABLE . '.DITTA_CG18'
                    )
                    ->on(
                        self::ORDER_VIEW_ALIAS . '.CLIFOR_CG44',
                        '=',
                        self::HEADER_TABLE . '.CLIFOR_CG44'
                    );
            }
        );
    }

    public function scopeWithDocumentDetails(Builder $query): Builder
    {
        return $query
            ->withOrderProvenance()
            ->leftJoin(
                self::CUSTOMER_TABLE . ' as ' . self::CUSTOMER_TABLE_ALIAS,
                function (JoinClause $join) {
                    $join
                        ->on(
                            self::CUSTOMER_TABLE_ALIAS . '.DITTA_CG18',
                            '=',
                            self::HEADER_TABLE . '.DITTA_CG18'
                        )
                        ->on(
                            self::CUSTOMER_TABLE_ALIAS . '.CLIFOR_CG44',
                            '=',
                            self::HEADER_TABLE . '.CLIFOR_CG44'
                        );
                }
            )
            ->select([
                self::HEADER_TABLE . '.*',
                self::ORDER_VIEW_ALIAS . '.PROVENORD as PROVENORD',
                self::ORDER_VIEW_ALIAS . '.INDSPEDMERCE as INDSPEDMERCE',
                self::CUSTOMER_TABLE_ALIAS . '.RAGSOANAG_CG16 as CUSTOMER_RAGSOANAG',
                self::CUSTOMER_TABLE_ALIAS . '.INDIRIZZO_CG16 as CUSTOMER_INDIRIZZO',
                self::CUSTOMER_TABLE_ALIAS . '.CAP_CG16 as CUSTOMER_CAP',
                self::CUSTOMER_TABLE_ALIAS . '.CITTA_CG16 as CUSTOMER_CITTA',
                self::CUSTOMER_TABLE_ALIAS . '.PROV_CG16 as CUSTOMER_PROV',
                self::CUSTOMER_TABLE_ALIAS . '.PARTIVA_CG16 as CUSTOMER_PARTIVA',
                self::CUSTOMER_TABLE_ALIAS . '.CODFISCALE_CG16 as CUSTOMER_CODFISCALE',
                self::CUSTOMER_TABLE_ALIAS . '.INDEMAIL_CG16 as CUSTOMER_EMAIL',
                self::CUSTOMER_TABLE_ALIAS . '.TEL1NUM_CG16 as CUSTOMER_TELEFONO',
            ]);
    }

    public function scopeForCustomer(
        Builder $query,
        int $ditta,
        int $clifor
    ): Builder {
        return $query
            ->where(
                self::HEADER_TABLE . '.DITTA_CG18',
                $ditta
            )
            ->where(
                self::HEADER_TABLE . '.CLIFOR_CG44',
                $clifor
            );
    }

    public function scopeForDitte(
        Builder $query,
        array $ditte
    ): Builder {
        $ditte = array_values(
            array_unique(
                array_map('intval', $ditte)
            )
        );

        return $query->whereIn(
            self::HEADER_TABLE . '.DITTA_CG18',
            $ditte
        );
    }

    public function scopeStoreLocatorDocuments(
        Builder $query
    ): Builder {
        return $query->whereIn(
            self::HEADER_TABLE . '.TIPODOCDECOD_MG36',
            self::STORE_LOCATOR_DOCUMENT_TYPES
        );
    }

    public function scopeWithValidStoreLocatorCustomer(
        Builder $query
    ): Builder {
        return $query
            ->whereNotNull(
                self::HEADER_TABLE . '.CLIFOR_CG44'
            )
            ->where(
                self::HEADER_TABLE . '.CLIFOR_CG44',
                '>',
                0
            );
    }

    public function scopeApplyDocumentFilters(
        Builder $query,
        array $filters
    ): Builder {
        $documentNumber = trim(
            (string) ($filters['document_number'] ?? '')
        );

        $documentType = trim(
            (string) ($filters['document_type'] ?? '')
        );

        $dateFrom = self::normalizeDateForErp(
            $filters['date_from'] ?? null,
            false
        );

        $dateTo = self::normalizeDateForErp(
            $filters['date_to'] ?? null,
            true
        );

        return $query
            ->when(
                $documentNumber !== '',
                function (Builder $query) use ($documentNumber) {
                    $query->where(
                        function (Builder $query) use ($documentNumber) {
                            $query
                                ->where(
                                    self::HEADER_TABLE . '.NUMSEZDOC_DO11',
                                    'like',
                                    '%' . $documentNumber . '%'
                                )
                                ->orWhere(
                                    self::HEADER_TABLE . '.NUMREG_CO99',
                                    'like',
                                    '%' . $documentNumber . '%'
                                );
                        }
                    );
                }
            )
            ->when(
                $documentType !== '',
                fn (Builder $query) => $query->where(
                    self::HEADER_TABLE . '.TIPODOCDECOD_MG36',
                    $documentType
                )
            )
            ->when(
                $dateFrom !== null,
                fn (Builder $query) => $query->whereRaw(
                    self::ERP_DATE_EXPRESSION . ' >= ?',
                    [$dateFrom]
                )
            )
            ->when(
                $dateTo !== null,
                fn (Builder $query) => $query->whereRaw(
                    self::ERP_DATE_EXPRESSION . ' <= ?',
                    [$dateTo]
                )
            );
    }

    public function scopeOrderByDocumentDate(
        Builder $query,
        string $direction = 'desc'
    ): Builder {
        $direction = strtolower($direction) === 'asc'
            ? 'asc'
            : 'desc';

        return $query->orderByRaw(
            self::ERP_DATE_EXPRESSION . ' ' . $direction
        );
    }

    public function scopeOrderByDocumentNumber(
        Builder $query,
        string $direction = 'desc'
    ): Builder {
        $direction = strtolower($direction) === 'asc'
            ? 'asc'
            : 'desc';

        return $query->orderBy(
            self::HEADER_TABLE . '.NUMREG_CO99',
            $direction
        );
    }

    public function scopeVisibleDocumentTypes(
        Builder $query,
        ?string $selectedType = null
    ): Builder {
        $selectedType = trim((string) ($selectedType ?? ''));

        if ($selectedType !== '') {
            return $query->where(
                self::HEADER_TABLE . '.TIPODOCDECOD_MG36',
                $selectedType
            );
        }

        return $query->whereIn(
            self::HEADER_TABLE . '.TIPODOCDECOD_MG36',
            self::STORE_LOCATOR_DOCUMENT_TYPES
        );
    }

    public static function documentTypesForCustomer(
        int $ditta,
        int $clifor
    ): array {
        return static::query()
            ->forCustomer($ditta, $clifor)
            ->storeLocatorDocuments()
            ->whereNotNull(
                self::HEADER_TABLE . '.TIPODOCDECOD_MG36'
            )
            ->distinct()
            ->orderBy(
                self::HEADER_TABLE . '.TIPODOCDECOD_MG36'
            )
            ->pluck(
                self::HEADER_TABLE . '.TIPODOCDECOD_MG36'
            )
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function defaultDocumentTypes(
        ?string $selectedType = null
    ): array {
        $types = collect(self::STORE_LOCATOR_DOCUMENT_TYPES);

        $selectedType = trim((string) ($selectedType ?? ''));

        if (
            $selectedType !== ''
            && in_array(
                $selectedType,
                self::STORE_LOCATOR_DOCUMENT_TYPES,
                true
            )
        ) {
            $types->push($selectedType);
        }

        return $types
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function documentNumberForDisplay(): string
    {
        return trim(
            (string) ($this->NUMSEZDOC_DO11 ?? '')
        ) ?: '-';
    }

    public function documentTypeForDisplay(): string
    {
        return trim(
            (string) ($this->TIPODOCDECOD_MG36 ?? '')
        ) ?: '-';
    }

    public function provenanceForDisplay(): string
    {
        return $this->resolvedProvenance() ?: '-';
    }

    public function hasOrderProvenance(): bool
    {
        return $this->resolvedProvenance() !== '';
    }

    public function shippingAddressForDisplay(): string
    {
        return $this->resolvedShippingAddress() ?: '-';
    }

    public function goodsDestinationForDisplay(): string
    {
        return DocumentGoodsDestinationResolver::format(
            $this->getAttribute('document_goods_destination')
        );
    }

    public static function preloadDdtOrderFallbackDetails(iterable $documents): void
    {
        $documents = collect($documents)
            ->filter(fn ($document) => $document instanceof self)
            ->values();

        if ($documents->isEmpty()) {
            return;
        }

        $empty = [
            'shipping_address' => '',
            'provenance' => '',
        ];

        $candidates = $documents
            ->filter(function (self $document) {
                if (array_key_exists('ddt_order_fallback_details', $document->relations)) {
                    return false;
                }

                if (trim((string) ($document->PROVENORD ?? '')) !== '') {
                    return false;
                }

                $documentType = strtoupper(
                    trim((string) ($document->TIPODOCDECOD_MG36 ?? ''))
                );

                if (! str_starts_with($documentType, 'DDT')) {
                    return false;
                }

                return trim((string) ($document->NUMREG_CO99 ?? '')) !== ''
                    && (int) ($document->DITTA_CG18 ?? 0) > 0
                    && (int) ($document->CLIFOR_CG44 ?? 0) > 0;
            })
            ->values();

        if ($candidates->isEmpty()) {
            return;
        }

        $candidates->each(
            fn (self $document) => $document->relations['ddt_order_fallback_details'] = $empty
        );

        $candidates
            ->groupBy(fn (self $document) => implode(':', [
                (int) $document->DITTA_CG18,
                (int) $document->CLIFOR_CG44,
            ]))
            ->each(function ($group) {
                $first = $group->first();

                if (! $first instanceof self) {
                    return;
                }

                $ditta = (int) $first->DITTA_CG18;
                $clifor = (int) $first->CLIFOR_CG44;

                $documentNumbers = $group
                    ->map(fn (self $document) => trim((string) $document->NUMREG_CO99))
                    ->filter()
                    ->unique()
                    ->values();

                if ($documentNumbers->isEmpty()) {
                    return;
                }

                $references = $first->getConnection()
                    ->table(self::DOCUMENT_REFERENCES_TABLE)
                    ->where('DITTA_CG18', $ditta)
                    ->where('CLIFOR_CG44', $clifor)
                    ->whereIn(
                        $first->getConnection()->raw(
                            'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_DDT)))'
                        ),
                        $documentNumbers->all()
                    )
                    ->whereNotNull('NUMREG_CO99_ORD')
                    ->selectRaw(
                        'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_DDT))) as NUMREG_CO99_DDT, ' .
                        'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_ORD))) as NUMREG_CO99_ORD'
                    )
                    ->get()
                    ->map(function ($reference) {
                        return [
                            'ddt' => trim((string) ($reference->NUMREG_CO99_DDT ?? '')),
                            'order' => trim((string) ($reference->NUMREG_CO99_ORD ?? '')),
                        ];
                    })
                    ->filter(fn (array $reference) => $reference['ddt'] !== '' && $reference['order'] !== '');

                if ($references->isEmpty()) {
                    return;
                }

                $orderNumbers = $references
                    ->pluck('order')
                    ->unique()
                    ->values();

                $orderDetails = $first->getConnection()
                    ->table(self::ORDER_VIEW)
                    ->where('DITTA_CG18', $ditta)
                    ->where('CLIFOR_CG44', $clifor)
                    ->whereIn('NUMREG_CO99', $orderNumbers->all())
                    ->get([
                        'NUMREG_CO99',
                        'PROVENORD',
                        'INDSPEDMERCE',
                    ])
                    ->keyBy(fn ($order) => trim((string) ($order->NUMREG_CO99 ?? '')));

                $detailsByDdt = $references
                    ->groupBy('ddt')
                    ->map(function ($ddtReferences) use ($orderDetails) {
                        $orders = $ddtReferences
                            ->pluck('order')
                            ->unique()
                            ->map(fn (string $orderNumber) => $orderDetails->get($orderNumber))
                            ->filter();

                        $shippingAddresses = $orders
                            ->pluck('INDSPEDMERCE')
                            ->map(fn ($value) => trim((string) $value))
                            ->filter()
                            ->unique()
                            ->values();

                        $provenances = $orders
                            ->pluck('PROVENORD')
                            ->map(fn ($value) => trim((string) $value))
                            ->filter()
                            ->unique()
                            ->values();

                        return [
                            'shipping_address' => $shippingAddresses->count() === 1
                                ? (string) $shippingAddresses->first()
                                : '',
                            'provenance' => $provenances->count() === 1
                                ? (string) $provenances->first()
                                : '',
                        ];
                    });

                $group->each(function (self $document) use ($detailsByDdt) {
                    $numreg = trim((string) $document->NUMREG_CO99);

                    if ($detailsByDdt->has($numreg)) {
                        $document->relations['ddt_order_fallback_details'] = $detailsByDdt->get($numreg);
                    }
                });
            });
    }

    private function resolvedShippingAddress(): string
    {
        $goodsDestination = $this->goodsDestinationForDisplay();

        if ($goodsDestination !== '') {
            return $goodsDestination;
        }

        $direct = trim(
            (string) ($this->INDSPEDMERCE ?? '')
        );

        if ($direct !== '') {
            return $direct;
        }

        return $this->ddtOrderFallbackDetails()['shipping_address'];
    }

    private function resolvedProvenance(): string
    {
        $direct = trim(
            (string) ($this->PROVENORD ?? '')
        );

        if ($direct !== '') {
            return $direct;
        }

        return $this->ddtOrderFallbackDetails()['provenance'];
    }

    private function ddtOrderFallbackDetails(): array
    {
        if (array_key_exists('ddt_order_fallback_details', $this->relations)) {
            return $this->relations['ddt_order_fallback_details'];
        }

        $empty = [
            'shipping_address' => '',
            'provenance' => '',
        ];

        $documentType = strtoupper(
            trim((string) ($this->TIPODOCDECOD_MG36 ?? ''))
        );

        if (! str_starts_with($documentType, 'DDT')) {
            $this->relations['ddt_order_fallback_details'] = $empty;

            return $empty;
        }

        $numreg = trim((string) ($this->NUMREG_CO99 ?? ''));
        $ditta = (int) ($this->DITTA_CG18 ?? 0);
        $clifor = (int) ($this->CLIFOR_CG44 ?? 0);

        if ($numreg === '' || $ditta <= 0 || $clifor <= 0) {
            $this->relations['ddt_order_fallback_details'] = $empty;

            return $empty;
        }

        $orderNumbers = $this->getConnection()
            ->table(self::DOCUMENT_REFERENCES_TABLE)
            ->where('DITTA_CG18', $ditta)
            ->where('CLIFOR_CG44', $clifor)
            ->whereRaw(
                'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_DDT))) = ?',
                [$numreg]
            )
            ->whereNotNull('NUMREG_CO99_ORD')
            ->selectRaw(
                'DISTINCT LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_ORD))) as NUMREG_CO99_ORD'
            )
            ->pluck('NUMREG_CO99_ORD')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($orderNumbers->isEmpty()) {
            $this->relations['ddt_order_fallback_details'] = $empty;

            return $empty;
        }

        $orderDetails = $this->getConnection()
            ->table(self::ORDER_VIEW)
            ->where('DITTA_CG18', $ditta)
            ->where('CLIFOR_CG44', $clifor)
            ->whereIn('NUMREG_CO99', $orderNumbers->all())
            ->get([
                'NUMREG_CO99',
                'PROVENORD',
                'INDSPEDMERCE',
            ]);

        $shippingAddresses = $orderDetails
            ->pluck('INDSPEDMERCE')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $provenances = $orderDetails
            ->pluck('PROVENORD')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $details = [
            'shipping_address' => $shippingAddresses->count() === 1
                ? (string) $shippingAddresses->first()
                : '',
            'provenance' => $provenances->count() === 1
                ? (string) $provenances->first()
                : '',
        ];

        $this->relations['ddt_order_fallback_details'] = $details;

        return $details;
    }

    public function customerNameForDisplay(): string
    {
        return trim(
            (string) ($this->CUSTOMER_RAGSOANAG ?? '')
        ) ?: '-';
    }

    public function customerAddressForDisplay(): string
    {
        return trim(
            (string) ($this->CUSTOMER_INDIRIZZO ?? '')
        ) ?: '-';
    }

    public function customerCityForDisplay(): string
    {
        $parts = array_filter([
            trim((string) ($this->CUSTOMER_CAP ?? '')),
            trim((string) ($this->CUSTOMER_CITTA ?? '')),
        ]);

        $value = implode(' ', $parts);

        $province = trim(
            (string) ($this->CUSTOMER_PROV ?? '')
        );

        if ($province !== '') {
            $value .= ($value !== '' ? ' ' : '') . '(' . $province . ')';
        }

        return $value !== '' ? $value : '-';
    }

    public function customerVatNumberForDisplay(): string
    {
        return trim(
            (string) ($this->CUSTOMER_PARTIVA ?? '')
        ) ?: '-';
    }

    public function customerTaxCodeForDisplay(): string
    {
        return trim(
            (string) ($this->CUSTOMER_CODFISCALE ?? '')
        ) ?: '-';
    }

    public function customerEmailForDisplay(): string
    {
        return trim(
            (string) ($this->CUSTOMER_EMAIL ?? '')
        ) ?: '-';
    }

    public function customerPhoneForDisplay(): string
    {
        return trim(
            (string) ($this->CUSTOMER_TELEFONO ?? '')
        ) ?: '-';
    }

    private static function normalizeDateForErp(
        mixed $value,
        bool $endOfDay = false
    ): ?string {
        $date = trim((string) ($value ?? ''));

        if ($date === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $parsed = Carbon::createFromFormat(
                    'Y-m-d',
                    $date
                );
            } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                $parsed = Carbon::createFromFormat(
                    'd/m/Y',
                    $date
                );
            } else {
                return null;
            }

            $parsed = $endOfDay
                ? $parsed->endOfDay()
                : $parsed->startOfDay();

            return $parsed->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
