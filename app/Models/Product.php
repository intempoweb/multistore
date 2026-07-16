<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    protected $fillable = [
        'ditta_cg18',
        'site_type',
        'sku',
        'parent_code',
        'parent_site_type',
        'type',
        'is_active',
        'no_backorder',
        'stock_qty',
        'flgmodultime_webt01',
        'flgintempo_webt01',
        'flgstaging_webt01',
        'codgrupfis_mg61',
        'barcode',
        'unit',
        'min_order_qty',
        'fam_99',
        'sfam_99',
        'gruppo_99',
        'sgruppo_99',
        'marca_mg64',
        'gruattr01_w11',
        'gruattr02_w12',
        'gruattr03_w13',
        'gruattr04_w14',
        'gruattr05_w15',
        'gruattr06_w16',
        'gruattr07_w17',
        'gruattr08_w18',
        'gruattr09_w19',
        'gruattr10_w20',
        'gruattr11_w21',
        'gruattr12_w22',
        'gruattr13_w23',
        'gruattr14_w24',
        'gruattr15_w25',
        'gruattr16_w26',
        'gruattr17_w27',
        'gruattr18_w28',
        'gruattr19_w29',
        'gruattr20_w30',
        'gruattr21_w31',
        'gruattr22_w32',
        'gruattr23_w33',
        'gruattr24_w34',
        'gruattr25_w35',
        'gruattr26_w36',
        'gruattr27_w37',
        'gruattr28_w38',
        'gruattr29_w39',
        'gruattr30_w40',
        'gruattr31_w41',
        'gruattr32_w42',
        'gruattr33_w43',
        'gruattr34_w44',
        'gruattr35_w45',
        'gruattr36_w46',
        'gruattr37_w47',
        'gruattr38_w48',
        'gruattr39_w49',
        'gruattr40_w50',
        'opzionefam_webt01',
        'opzioneraggr_webt01',
        'raggrupcat1_w51',
        'raggrupcat2_w52',
        'raggrupcat3_w53',
        'raggrupcat4_w54',
        'codlinea_w55',
        'codedizione_w56',
        'codcollezione_w57',
        'codbrand_w58',
        'codfantasie_w59',
        'codassociazioneart_w60',
        'raggrupassoc1_w61',
        'raggrupassoc2_w62',
        'raggrupassoc3_w63',
        'raggrupassoc4_w64',
        'pagcatalogo_webt01',
        'flgofferta_webt01',
        'datainizofferta_webt01',
        'datafineofferta_webt01',
        'flgpromo_webt01',
        'datainizpromo_webt01',
        'datafinepromo_webt01',
        'flgnovita_webt01',
        'datainiznovita_webt01',
        'datafinenovita_webt01',
        'flgcampagna_webt01',
        'datainizcampagna_webt01',
        'datafinecampagna_webt01',
        'qtamaxvisibile_webt01',
        'flgsemaforo_webt01',
        'qtasemafverde_webt01',
        'qtasemafarancio_webt01',
        'qtasemafrosso_webt01',
        'notedepprel_mg69',
        'codconfez_mg96',
        'pzconf_mg68',
        'pesocalc',
        'umpeso_mg68',
        'peson_mg68',
        'pesol_mg68',
        'massanetta_mg98',
        'largh_mg68',
        'altez_mg68',
        'prof_mg68',
        'erp_dataultimoagg',
        'erp_lastchange',

        'public_price',
        'public_price_listino_id',
        'public_price_gross',
        'public_price_lastchange',
        'public_price_last_seen_at',
    ];

    protected $casts = [
        'ditta_cg18'                => 'integer',
        'site_type'                 => 'integer',
        'parent_site_type'          => 'integer',
        'is_active'                 => 'boolean',
        'no_backorder'              => 'boolean',
        'stock_qty'                 => 'decimal:3',
        'flgmodultime_webt01'       => 'boolean',
        'flgintempo_webt01'         => 'boolean',
        'flgstaging_webt01'         => 'boolean',
        'min_order_qty'             => 'integer',
        'fam_99'                    => 'string',
        'sfam_99'                   => 'string',
        'gruppo_99'                 => 'string',
        'sgruppo_99'                => 'string',
        'marca_mg64'                => 'string',
        'gruattr01_w11'             => 'string',
        'gruattr02_w12'             => 'string',
        'gruattr03_w13'             => 'string',
        'gruattr04_w14'             => 'string',
        'gruattr05_w15'             => 'string',
        'gruattr06_w16'             => 'string',
        'gruattr07_w17'             => 'string',
        'gruattr08_w18'             => 'string',
        'gruattr09_w19'             => 'string',
        'gruattr10_w20'             => 'string',
        'gruattr11_w21'             => 'string',
        'gruattr12_w22'             => 'string',
        'gruattr13_w23'             => 'string',
        'gruattr14_w24'             => 'string',
        'gruattr15_w25'             => 'string',
        'gruattr16_w26'             => 'string',
        'gruattr17_w27'             => 'string',
        'gruattr18_w28'             => 'string',
        'gruattr19_w29'             => 'string',
        'gruattr20_w30'             => 'string',
        'gruattr21_w31'             => 'string',
        'gruattr22_w32'             => 'string',
        'gruattr23_w33'             => 'string',
        'gruattr24_w34'             => 'string',
        'gruattr25_w35'             => 'string',
        'gruattr26_w36'             => 'string',
        'gruattr27_w37'             => 'string',
        'gruattr28_w38'             => 'string',
        'gruattr29_w39'             => 'string',
        'gruattr30_w40'             => 'string',
        'gruattr31_w41'             => 'string',
        'gruattr32_w42'             => 'string',
        'gruattr33_w43'             => 'string',
        'gruattr34_w44'             => 'string',
        'gruattr35_w45'             => 'string',
        'gruattr36_w46'             => 'string',
        'gruattr37_w47'             => 'string',
        'gruattr38_w48'             => 'string',
        'gruattr39_w49'             => 'string',
        'gruattr40_w50'             => 'string',
        'flgofferta_webt01'         => 'boolean',
        'flgpromo_webt01'           => 'boolean',
        'flgnovita_webt01'          => 'boolean',
        'flgcampagna_webt01'        => 'boolean',
        'flgsemaforo_webt01'        => 'boolean',
        'qtamaxvisibile_webt01'     => 'decimal:3',
        'qtasemafverde_webt01'      => 'decimal:3',
        'qtasemafarancio_webt01'    => 'decimal:3',
        'qtasemafrosso_webt01'      => 'decimal:3',
        'pzconf_mg68'               => 'decimal:3',
        'pesocalc'                  => 'decimal:4',
        'peson_mg68'                => 'decimal:4',
        'pesol_mg68'                => 'decimal:4',
        'massanetta_mg98'           => 'decimal:6',
        'largh_mg68'                => 'decimal:4',
        'altez_mg68'                => 'decimal:4',
        'prof_mg68'                 => 'decimal:4',
        'erp_dataultimoagg'         => 'date',
        'erp_lastchange'            => 'datetime',
        'datainizofferta_webt01'    => 'date',
        'datafineofferta_webt01'    => 'date',
        'datainizpromo_webt01'      => 'date',
        'datafinepromo_webt01'      => 'date',
        'datainiznovita_webt01'     => 'date',
        'datafinenovita_webt01'     => 'date',
        'datainizcampagna_webt01'   => 'date',
        'datafinecampagna_webt01'   => 'date',
        'public_price'              => 'decimal:2',
        'public_price_listino_id'   => 'integer',
        'public_price_gross'        => 'decimal:6',
        'public_price_lastchange'   => 'date',
        'public_price_last_seen_at' => 'datetime',
    ];

    public function scopeForContext(Builder $q, int $ditta, int $siteType): Builder
    {
        return $q->where('ditta_cg18', $ditta)->where('site_type', $siteType);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSimple(Builder $q): Builder
    {
        return $q->where('type', 'simple');
    }

    public function scopeConfigurable(Builder $q): Builder
    {
        return $q->where('type', 'configurable');
    }

    public function scopeForCategoryTree(
        Builder $q,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null
    ): Builder {
        if (($fam = self::normalizeErpCodeValue($fam)) !== null) {
            $q->where('fam_99', $fam);
        }

        if (($sfam = self::normalizeErpCodeValue($sfam)) !== null) {
            $q->where('sfam_99', $sfam);
        }

        if (($gruppo = self::normalizeErpCodeValue($gruppo)) !== null) {
            $q->where('gruppo_99', $gruppo);
        }

        if (($sgruppo = self::normalizeErpCodeValue($sgruppo)) !== null) {
            $q->where('sgruppo_99', $sgruppo);
        }

        return $q;
    }

    public function scopeVisibleForCustomer(Builder $q, int $ditta, int $siteType, int $tipocf, int $clifor): Builder
    {
        return $q
            ->where('products.ditta_cg18', $ditta)
            ->where('products.site_type', $siteType)
            ->where('products.is_active', true)
            ->where(function (Builder $outer) use ($siteType, $tipocf, $clifor) {
                $outer->where(function (Builder $simple) use ($siteType, $tipocf, $clifor) {
                    $simple->where('products.type', 'simple')
                        ->whereNotNull('products.codgrupfis_mg61')
                        ->whereExists(function ($sub) use ($siteType) {
                            $sub->selectRaw('1')
                                ->from('store_visible_groups as svg')
                                ->whereColumn('svg.ditta_cg18', 'products.ditta_cg18')
                                ->where('svg.site_type', $siteType)
                                ->whereColumn('svg.codice_xx32', 'products.codgrupfis_mg61');
                        })
                        ->where(function (Builder $customerVisibility) use ($tipocf, $clifor) {
                            $customerVisibility
                                ->whereExists(function ($sub) use ($tipocf, $clifor) {
                                    $sub->selectRaw('1')
                                        ->from('customer_visible_groups as cvg')
                                        ->whereColumn('cvg.ditta_cg18', 'products.ditta_cg18')
                                        ->where('cvg.tipocf_cg44', $tipocf)
                                        ->where('cvg.clifor_cg44', $clifor)
                                        ->where('cvg.is_active', 1)
                                        ->whereColumn('cvg.codice_xx32', 'products.codgrupfis_mg61');
                                })
                                ->orWhereNotExists(function ($sub) use ($tipocf, $clifor) {
                                    $sub->selectRaw('1')
                                        ->from('customer_visible_groups as cvg_any')
                                        ->whereColumn('cvg_any.ditta_cg18', 'products.ditta_cg18')
                                        ->where('cvg_any.tipocf_cg44', $tipocf)
                                        ->where('cvg_any.clifor_cg44', $clifor)
                                        ->where('cvg_any.is_active', 1);
                                });
                        });
                });

                $outer->orWhere(function (Builder $parent) use ($siteType, $tipocf, $clifor) {
                    $parent->where('products.type', 'configurable')
                        ->whereExists(function ($sub) use ($siteType, $tipocf, $clifor) {
                            $sub->selectRaw('1')
                                ->from('products as c')
                                ->whereColumn('c.ditta_cg18', 'products.ditta_cg18')
                                ->whereColumn('c.site_type', 'products.site_type')
                                ->whereColumn('c.parent_code', 'products.sku')
                                ->where('c.type', 'simple')
                                ->where('c.is_active', 1)
                                ->whereNotNull('c.codgrupfis_mg61')
                                ->whereExists(function ($sub2) use ($siteType) {
                                    $sub2->selectRaw('1')
                                        ->from('store_visible_groups as svg')
                                        ->whereColumn('svg.ditta_cg18', 'c.ditta_cg18')
                                        ->where('svg.site_type', $siteType)
                                        ->whereColumn('svg.codice_xx32', 'c.codgrupfis_mg61');
                                })
                                ->where(function ($customerVisibility) use ($tipocf, $clifor) {
                                    $customerVisibility
                                        ->whereExists(function ($sub2) use ($tipocf, $clifor) {
                                            $sub2->selectRaw('1')
                                                ->from('customer_visible_groups as cvg')
                                                ->whereColumn('cvg.ditta_cg18', 'c.ditta_cg18')
                                                ->where('cvg.tipocf_cg44', $tipocf)
                                                ->where('cvg.clifor_cg44', $clifor)
                                                ->where('cvg.is_active', 1)
                                                ->whereColumn('cvg.codice_xx32', 'c.codgrupfis_mg61');
                                        })
                                        ->orWhereNotExists(function ($sub2) use ($tipocf, $clifor) {
                                            $sub2->selectRaw('1')
                                                ->from('customer_visible_groups as cvg_any')
                                                ->whereColumn('cvg_any.ditta_cg18', 'c.ditta_cg18')
                                                ->where('cvg_any.tipocf_cg44', $tipocf)
                                                ->where('cvg_any.clifor_cg44', $clifor)
                                                ->where('cvg_any.is_active', 1);
                                        });
                                });
                        });
                });
            });
    }

    public function parent(): BelongsTo
    {
        $parentSite = (int) ($this->parent_site_type ?? $this->site_type);

        return $this->belongsTo(self::class, 'parent_code', 'sku')
            ->where('type', 'configurable')
            ->where('ditta_cg18', (int) $this->ditta_cg18)
            ->where('site_type', $parentSite);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_code', 'sku')
            ->where('type', 'simple')
            ->where('ditta_cg18', (int) $this->ditta_cg18)
            ->where('site_type', (int) $this->site_type);
    }

    public function configurable(): HasOne
    {
        return $this->hasOne(ConfigurableProduct::class, 'parent_code', 'sku')
            ->where('ditta_cg18', (int) $this->ditta_cg18)
            ->where('site_type', (int) $this->site_type);
    }

    public function comparisons(): HasMany
    {
        return $this->hasMany(ProductComparison::class, 'sku', 'sku')
            ->where('ditta_cg18', (int) $this->ditta_cg18)
            ->where('site_type', (int) $this->site_type)
            ->orderBy('source')
            ->orderBy('comparison_sku')
            ->orderBy('id');
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'product_id', 'id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
                AttributeValue::class,
                'product_attribute_values',
                'product_id',
                'attribute_value_id'
            )
            ->withPivot(['attribute_id', 'raw_value', 'value_key', 'erp_lastchange'])
            ->withTimestamps();
    }

    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'mediable')
            ->orderBy('sort_order');
    }

    public function mainImage(): ?MediaAsset
    {
        return $this->mediaAssets()
            ->where('role', MediaAsset::ROLE_MAIN)
            ->orderBy('sort_order')
            ->first();
    }

    public function galleryImages(): MorphMany
    {
        return $this->mediaAssets()
            ->where('role', MediaAsset::ROLE_GALLERY)
            ->orderBy('sort_order');
    }

    public function getCategoryTreeAttribute(): array
    {
        return [
            'fam_99'     => self::normalizeErpCodeValue($this->fam_99),
            'sfam_99'    => self::normalizeErpCodeValue($this->sfam_99),
            'gruppo_99'  => self::normalizeErpCodeValue($this->gruppo_99),
            'sgruppo_99' => self::normalizeErpCodeValue($this->sgruppo_99),
        ];
    }

    public function hasCategoryTree(): bool
    {
        return self::normalizeErpCodeValue($this->fam_99) !== null
            || self::normalizeErpCodeValue($this->sfam_99) !== null
            || self::normalizeErpCodeValue($this->gruppo_99) !== null
            || self::normalizeErpCodeValue($this->sgruppo_99) !== null;
    }

    public function categoryTreeKey(string $separator = '>'): ?string
    {
        $parts = array_values(array_filter([
            self::normalizeErpCodeValue($this->fam_99),
            self::normalizeErpCodeValue($this->sfam_99),
            self::normalizeErpCodeValue($this->gruppo_99),
            self::normalizeErpCodeValue($this->sgruppo_99),
        ]));

        return empty($parts) ? null : implode($separator, $parts);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class, 'product_id', 'id');
    }

    public function translation(string $locale): ?ProductTranslation
    {
        return $this->translations()->where('locale', $locale)->first();
    }

    public function translationOrFallback(string $locale, ?string $fallback = null): ?ProductTranslation
    {
        $fallback ??= config('app.fallback_locale', 'en');

        return $this->translation($locale)
            ?: $this->translation($fallback)
            ?: $this->translations()->first();
    }

    public static function normalizeErpCodeValue(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
    
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(CustomerWishlistItem::class, 'product_id', 'id');
    }
}
