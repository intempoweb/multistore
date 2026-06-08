<?php

namespace App\Services\Erp;

use App\Models\Attribute;
use App\Models\AttributeTranslation;
use App\Models\AttributeValue;
use App\Models\AttributeValueTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AttributeSyncService
{
    private const LOCALE_MAP = [
        'ITA' => 'it',
        'IT'  => 'it',
        'GB'  => 'en',
        'EN'  => 'en',
        'USA' => 'en',
    ];

    private static bool $erpSessionInitialized = false;

    /**
     * ✅ FIX SQL Server:
     * Heterogeneous queries require ANSI_NULLS + ANSI_WARNINGS ON
     */
    private function initErpSession(): void
    {
        if (self::$erpSessionInitialized) return;

        $conn = DB::connection('erp');
        $conn->statement('SET ANSI_NULLS ON');
        $conn->statement('SET ANSI_WARNINGS ON');

        self::$erpSessionInitialized = true;
    }

    /**
     * Sync TUTTI gli attributi + valori + traduzioni.
     * ✅ GLOBALI: non più per ditta.
     *
     * @param bool $dryRun
     * @return array{attributes:int, values:int, attr_translations:int, value_translations:int}
     */
    public function sync(bool $dryRun = false): array
    {
        $this->initErpSession();

        $stats = [
            'attributes' => 0,
            'values' => 0,
            'attr_translations' => 0,
            'value_translations' => 0,
        ];

        try {
            /**
             * ERP source:
             * dbo.RAGGRATTRIBCOMM_TOT contiene attributi+valori+lingue.
             * Qui facciamo:
             * 1) creare attribute (per code) una sola volta
             * 2) upsert traduzioni attributo
             * 3) upsert value (per attribute_id + value_code)
             * 4) upsert traduzioni value
             */
            $rows = DB::connection('erp')
                ->table('dbo.RAGGRATTRIBCOMM_TOT')
                ->select([
                    'TABCODGRATTR_WEBT05',     // code attributo
                    'NOMEATTR_WEBT05',         // label attributo (dipende lingua)
                    'ATTRIB_CODGRUAT_TOT',     // code valore
                    'ATTRIB_DESCGRUAT_TOT',    // label valore (dipende lingua)
                    'ATTRIB_LINGUA_TOT',       // lingua
                ])
                ->get();

            if ($rows->isEmpty()) {
                return $stats;
            }

            /** @var array<string,int> $attrIdByCode */
            $attrIdByCode = [];

            /** @var array<string,int> $valueIdByAttrAndCode */
            $valueIdByAttrAndCode = [];

            foreach ($rows as $r) {
                $attrCode = trim((string) ($r->TABCODGRATTR_WEBT05 ?? ''));
                if ($attrCode === '') continue;

                $attrName = trim((string) ($r->NOMEATTR_WEBT05 ?? ''));
                $valueCode = trim((string) ($r->ATTRIB_CODGRUAT_TOT ?? ''));  // può essere vuoto (attributo senza dizionario)
                $valueLbl  = trim((string) ($r->ATTRIB_DESCGRUAT_TOT ?? ''));
                $langRaw   = trim((string) ($r->ATTRIB_LINGUA_TOT ?? ''));
                $locale    = $this->mapLocale($langRaw);

                // 1) Attribute (globale)
                if (!isset($attrIdByCode[$attrCode])) {
                    if ($dryRun) {
                        $attrIdByCode[$attrCode] = -1;
                    } else {
                        $attribute = Attribute::updateOrCreate(
                            ['code' => $attrCode],
                            [
                                'type'           => 'select',
                                'is_filterable'  => true,
                                'is_variant'     => false,
                                'sort_order'     => 0,
                                'erp_lastchange' => null,
                            ]
                        );
                        $attrIdByCode[$attrCode] = (int) $attribute->id;
                    }

                    $stats['attributes']++;
                }

                $attributeId = $attrIdByCode[$attrCode];

                // 2) AttributeTranslation
                if ($locale && $attrName !== '') {
                    if (!$dryRun && $attributeId > 0) {
                        AttributeTranslation::updateOrCreate(
                            [
                                'attribute_id' => $attributeId,
                                'locale'       => $locale,
                            ],
                            [
                                'label'     => $attrName,
                                'help_text' => null,
                            ]
                        );
                    }
                    $stats['attr_translations']++;
                }

                // 3) AttributeValue (solo se esiste valueCode)
                if ($valueCode === '') {
                    continue;
                }

                $valKey = $attributeId . ':' . $valueCode;

                if (!isset($valueIdByAttrAndCode[$valKey])) {
                    if ($dryRun || $attributeId <= 0) {
                        $valueIdByAttrAndCode[$valKey] = -1;
                    } else {
                        $attrValue = AttributeValue::updateOrCreate(
                            [
                                'attribute_id' => $attributeId,
                                'value_code'   => $valueCode,
                            ],
                            [
                                'sort_order'     => 0,
                                'erp_lastchange' => null,
                            ]
                        );
                        $valueIdByAttrAndCode[$valKey] = (int) $attrValue->id;
                    }

                    $stats['values']++;
                }

                $attrValueId = $valueIdByAttrAndCode[$valKey];

                // 4) AttributeValueTranslation
                if ($locale && $valueLbl !== '') {
                    if (!$dryRun && $attrValueId > 0) {
                        AttributeValueTranslation::updateOrCreate(
                            [
                                'attribute_value_id' => $attrValueId,
                                'locale'             => $locale,
                            ],
                            [
                                'label' => $valueLbl,
                            ]
                        );
                    }
                    $stats['value_translations']++;
                }
            }

            return $stats;

        } catch (Throwable $e) {
            Log::error('ERP Attribute Sync failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Preview rapido per debug (senza DB locale).
     */
    public function preview(string $attrCode, int $limit = 50): array
    {
        $this->initErpSession();

        $attrCode = trim($attrCode);

        $rows = DB::connection('erp')
            ->table('dbo.RAGGRATTRIBCOMM_TOT')
            ->select([
                'TABCODGRATTR_WEBT05',
                'NOMEATTR_WEBT05',
                'ATTRIB_CODGRUAT_TOT',
                'ATTRIB_DESCGRUAT_TOT',
                'ATTRIB_LINGUA_TOT',
            ])
            ->whereRaw('RTRIM(LTRIM(TABCODGRATTR_WEBT05)) = ?', [$attrCode])
            ->orderByRaw("RTRIM(LTRIM(ATTRIB_LINGUA_TOT))")
            ->orderByRaw("RTRIM(LTRIM(ATTRIB_CODGRUAT_TOT))")
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) {
            $lang = trim((string) ($r->ATTRIB_LINGUA_TOT ?? ''));

            return [
                'attr_code'  => trim((string) ($r->TABCODGRATTR_WEBT05 ?? '')),
                'attr_name'  => trim((string) ($r->NOMEATTR_WEBT05 ?? '')),
                'value_code' => trim((string) ($r->ATTRIB_CODGRUAT_TOT ?? '')),
                'value_lbl'  => trim((string) ($r->ATTRIB_DESCGRUAT_TOT ?? '')),
                'lang'       => $lang,
                'locale'     => $this->mapLocale($lang),
            ];
        })->all();
    }

    private function mapLocale(string $erpLang): ?string
    {
        $erpLang = strtoupper(trim($erpLang));
        return $erpLang !== '' ? (self::LOCALE_MAP[$erpLang] ?? null) : null;
    }
}