<?php

namespace App\Models\Erp;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public const STORE_LOCATOR_DOCUMENT_TYPES = [
        'DDT',
        'DDT RESO',
        'DDT_VARI',
        'FATT DDT',
        'FATTURA',
        'FATTURA RIEP',
        'ORDINE',
    ];

    public const INDEX_COLUMNS = [
        'NUMREG_CO99',
        'DITTA_CG18',
        'CLIFOR_CG44',
        'DATADOC_DO11',
        'NUMSEZDOC_DO11',
        'TIPODOCDECOD_MG36',
    ];

    private const ERP_DATE_EXPRESSION =
        'TRY_CONVERT(datetime, DATADOC_DO11, 103)';

    public function rows(): HasMany
    {
        return $this->hasMany(
            DocumentRow::class,
            'NUMREG_CO99',
            'NUMREG_CO99'
        )->orderBy('PROGRIGA_DO30');
    }

    public function scopeForCustomer(
        Builder $query,
        int $ditta,
        int $clifor
    ): Builder {
        return $query
            ->where('DITTA_CG18', $ditta)
            ->where('CLIFOR_CG44', $clifor);
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

        return $query->whereIn('DITTA_CG18', $ditte);
    }

    public function scopeStoreLocatorDocuments(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'TIPODOCDECOD_MG36',
            self::STORE_LOCATOR_DOCUMENT_TYPES
        );
    }

    public function scopeWithValidStoreLocatorCustomer(
        Builder $query
    ): Builder {
        return $query
            ->whereNotNull('CLIFOR_CG44')
            ->where('CLIFOR_CG44', '>', 0);
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
                                    'NUMSEZDOC_DO11',
                                    'like',
                                    '%' . $documentNumber . '%'
                                )
                                ->orWhere(
                                    'NUMREG_CO99',
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
                    'TIPODOCDECOD_MG36',
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

    public function scopeVisibleDocumentTypes(
        Builder $query,
        ?string $selectedType = null
    ): Builder {
        $selectedType = trim((string) ($selectedType ?? ''));

        if ($selectedType !== '') {
            return $query->where(
                'TIPODOCDECOD_MG36',
                $selectedType
            );
        }

        return $query->whereIn(
            'TIPODOCDECOD_MG36',
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
            ->whereNotNull('TIPODOCDECOD_MG36')
            ->distinct()
            ->orderBy('TIPODOCDECOD_MG36')
            ->pluck('TIPODOCDECOD_MG36')
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