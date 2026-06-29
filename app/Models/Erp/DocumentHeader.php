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

    public function rows(): HasMany
    {
        return $this->hasMany(DocumentRow::class, 'NUMREG_CO99', 'NUMREG_CO99')
            ->orderBy('PROGRIGA_DO30');
    }

    public function scopeForCustomer(Builder $query, int $ditta, int $clifor): Builder
    {
        return $query
            ->where('DITTA_CG18', $ditta)
            ->where('CLIFOR_CG44', $clifor);
    }

    public function scopeForDitte(Builder $query, array $ditte): Builder
    {
        return $query->whereIn('DITTA_CG18', array_values(array_unique(array_map('intval', $ditte))));
    }

    public function scopeStoreLocatorDocuments(Builder $query): Builder
    {
        return $query->whereIn('TIPODOCDECOD_MG36', self::STORE_LOCATOR_DOCUMENT_TYPES);
    }

    public function scopeWithValidStoreLocatorCustomer(Builder $query): Builder
    {
        return $query
            ->whereNotNull('CLIFOR_CG44')
            ->where('CLIFOR_CG44', '>', 0);
    }

    public function scopeApplyDocumentFilters(Builder $query, array $filters): Builder
    {
        $documentNumber = trim((string) ($filters['document_number'] ?? ''));
        $documentType = trim((string) ($filters['document_type'] ?? ''));
        $dateFrom = self::normalizeDateForErp($filters['date_from'] ?? null);
        $dateTo = self::normalizeDateForErp($filters['date_to'] ?? null);

        return $query
            ->when($documentNumber !== '', function (Builder $query) use ($documentNumber) {
                $query->where(function (Builder $query) use ($documentNumber) {
                    $query
                        ->where('NUMSEZDOC_DO11', 'like', '%' . $documentNumber . '%')
                        ->orWhere('NUMREG_CO99', 'like', '%' . $documentNumber . '%');
                });
            })
            ->when($documentType !== '', function (Builder $query) use ($documentType) {
                $query->where('TIPODOCDECOD_MG36', $documentType);
            })
            ->when($dateFrom !== null, function (Builder $query) use ($dateFrom) {
                $query->where('DATADOC_DO11', '>=', $dateFrom);
            })
            ->when($dateTo !== null, function (Builder $query) use ($dateTo) {
                $query->where('DATADOC_DO11', '<=', $dateTo);
            });
    }

    public static function documentTypesForCustomer(int $ditta, int $clifor): array
    {
        return static::query()
            ->forCustomer($ditta, $clifor)
            ->whereNotNull('TIPODOCDECOD_MG36')
            ->distinct()
            ->orderBy('TIPODOCDECOD_MG36')
            ->pluck('TIPODOCDECOD_MG36')
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->values()
            ->all();
    }

    public function documentNumberForDisplay(): string
    {
        return trim((string) ($this->NUMSEZDOC_DO11 ?? '')) ?: '-';
    }

    public function documentTypeForDisplay(): string
    {
        return trim((string) ($this->TIPODOCDECOD_MG36 ?? '')) ?: '-';
    }

    private static function normalizeDateForErp(mixed $value): ?string
    {
        $date = trim((string) ($value ?? ''));

        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse(str_replace('/', '-', $date))->format('d/m/Y');
        } catch (Throwable) {
            return null;
        }
    }
}