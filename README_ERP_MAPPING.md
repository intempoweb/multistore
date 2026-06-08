# ERP ↔ Laravel Mapping (Multistore) — Work in progress

Questo documento descrive le tabelle create in Laravel e come mappano le tabelle ERP (dbcomm / SQL Server).
Viene aggiornato via via che aggiungiamo moduli (product, media, stock, prezzi, ecc.).

---

## Modulo: Attributes

### Tabelle Laravel

#### 1) `attributes`
Contiene gli attributi (es. A09 = colore, ecc.)

**Campi principali (Laravel)**
- `id` (PK)
- `code` (codice attributo)
- `type` (`select|text|number|boolean`)
- `is_filterable` (bool)
- `is_variant` (bool)
- `sort_order` (int)
- `erp_lastchange` (datetime, opzionale)
- timestamps

**Vincoli**
- `UNIQUE(code)` *(se gli attributi sono globali)*  
  oppure `UNIQUE(ditta_cg18, code)` *(se per-ditta — da evitare se gli attributi sono gli stessi per tutte)*

---

#### 2) `attribute_translations`
Traduzioni label/help_text dell’attributo.

**Campi**
- `id` (PK)
- `attribute_id` (FK → `attributes.id`)
- `locale` (es: it, en)
- `label`
- `help_text` (nullable)

**Vincoli**
- `UNIQUE(attribute_id, locale)`

---

#### 3) `attribute_values`
Dizionario valori dell’attributo (es. per A09: 22, 23, 25, 99…).

**Campi**
- `id` (PK)
- `attribute_id` (FK → `attributes.id`)
- `value_code` (es. "22")
- `sort_order`
- `erp_lastchange`
- timestamps

**Vincoli**
- `UNIQUE(attribute_id, value_code)`

---

#### 4) `attribute_value_translations`
Traduzioni label dei valori.

**Campi**
- `id` (PK)
- `attribute_value_id` (FK → `attribute_values.id`)
- `locale`
- `label`

**Vincoli**
- `UNIQUE(attribute_value_id, locale)`

---

## Fonte ERP (dbcomm / SQL Server)

### Tabella principale usata dal sync
#### `dbo.RAGGRATTRIBCOMM_TOT`
Usata da `app/Services/Erp/AttributeSyncService.php`

**Colonne ERP usate**
- `ATTRIB_DITTA_TOT` → (se per-ditta) `attributes.ditta_cg18` *(se globali: ignorata / solo filtro run)*
- `TABCODGRATTR_WEBT05` → `attributes.code`
- `NOMEATTR_WEBT05` → `attribute_translations.label`
- `ATTRIB_LINGUA_TOT` → mapping lingua → `locale`
- `ATTRIB_CODGRUAT_TOT` → `attribute_values.value_code`
- `ATTRIB_DESCGRUAT_TOT` → `attribute_value_translations.label`

### Mapping lingua
ERP → Laravel locale (attuale)
- ITA / IT → `it`
- EN / GB → `en`
(da estendere se arrivano altre lingue)

---

## Comandi Artisan

### Migrazioni (solo attributes)
- `php artisan migrate:fresh --path=database/migrations/2026_02_23_100133_create_attributes_table.php`
- `php artisan migrate --path=database/migrations/2026_02_23_100144_create_attribute_translations_table.php`
- `php artisan migrate --path=database/migrations/2026_02_23_100154_create_attribute_values_table.php`
- `php artisan migrate --path=database/migrations/2026_02_23_100201_create_attribute_value_translations_table.php`

### Sync attributes
- Dry-run: `php artisan erp:sync-attributes --dry`
- Run: `php artisan erp:sync-attributes`
- Filtra ditte: `php artisan erp:sync-attributes --ditte=1 --ditte=3`

---

## Note / TODO
- Decidere definitivamente: attributi globali (UNIQUE(code)) vs per-ditta (UNIQUE(ditta, code)).
- Aggiungere mapping “is_variant/is_filterable/sort_order” se ERP li espone in altre tabelle.
- Agganciare swatch colore (media) ai `attribute_values` (A09) con tabella media_assets.
# ERP ↔ Laravel Mapping (Multistore)

Questo documento descrive in modo completo il mapping tra ERP (SQL Server – DB `DBCOMM`) e il database Laravel (MySQL) del progetto Multistore.

Include:
- Stores
- Products (simple + configurable)
- Stock
- Attributes
- Product Attribute Values
- Media

---

# 1️⃣ STORES

## Service
`app/Services/Erp/StoreSyncService.php`

## Fonte ERP
`dbo.SITIWEBB2BEB2C`

| ERP | Laravel |
|------|----------|
| DITTA_CG18 | stores.ditta_cg18 |
| FLG_B2B_B2C | stores.erp_site_code |
| DESCRIZSITO | stores.name |

## Logica
Le combinazioni (ditta, site_id) vengono filtrate tramite mapping statico interno (`STORES_MAP`).

---

# 2️⃣ PRODUCTS (Simple)

## Service
`app/Services/Erp/ProductSyncService.php`

## Regola di selezione (watermark)
Vengono sincronizzati SOLO gli SKU per cui:

```
MAX(ARTDESC_TOT.LASTCHANGE_WEBT87) >= --since
```

⚠️ DATAULTIMOAGG_WEBT01 NON è usato come watermark.

---

## Fonte ERP

### `dbo.ANAGRARTWEB_WEBT01`

| ERP | Laravel |
|------|----------|
| DITTA_CG18 | products.ditta_cg18 |
| FLG_B2B_B2C_WEBT01 | products.site_type |
| CODART_MG66 | products.sku |
| RADICEARTIC_WEBT01 | products.parent_code |
| FLGATTIVO_WEBT01 | products.is_active |
| FLGNOORDINZERO_WEBT01 | products.no_backorder |
| CONFMINACQ_WEBT01 | products.min_order_qty |
| UNITAMISURA_WEBT01 | products.unit |
| CODBARCODE_MG65 | products.barcode |
| CODGRUPFIS_MG61 | products.codgrupfis_mg61 |
| DATAULTIMOAGG_WEBT01 | products.erp_dataultimoagg |

---

### `dbo.ARTDESC_TOT`

| ERP | Laravel |
|------|----------|
| LINGUA_MG52 | product_translations.locale |
| DESCART_MG87 | product_translations.name |
| DESCARTEST_MG87 | product_translations.description |
| NOTEART_MG87 | fallback description |
| LASTCHANGE_WEBT87 | watermark logico |

---

## Tabelle Laravel coinvolte

### `products`
Chiave logica:
```
(ditta_cg18, site_type, sku)
```

Campi principali:
- type = simple
- is_active
- parent_code
- no_backorder
- min_order_qty
- stock_qty (gestito separatamente)
- erp_dataultimoagg

---

# 3️⃣ PRODUCTS (Configurable / Padri)

## Fonte ERP
`dbo.ANAGRARTPADRE_TOT`

| ERP | Laravel |
|------|----------|
| CODARTPADRE_WEBT00 | products.sku |
| FLG_B2B_B2C_WEBT00 | products.site_type |
| DATAULTIMOAGG_WEBT00 | watermark |
| TITOLOARTPADRE_WEBT00 | product_translations.name |
| DESCRARTPADRE_WEBT00 | product_translations.description |
| FOTOARTPADRE_WEBT00 | configurable_products.photo |

## Tabelle Laravel

### `products`
- type = configurable

### `configurable_products`
- parent_code
- photo
- dataultimoagg

---

# 4️⃣ STOCK

## Service
`app/Services/Erp/StockSyncService.php`

## Fonte ERP
`dbo.MAGPROQTAUNICA`

| ERP | Laravel |
|------|----------|
| CODART_MG66 | products.sku |
| QGIACATT_MG70 | products.stock_qty |
| DATAULTVAR_MG70 | watermark stock |

## Note
- Aggiorna SOLO prodotti `type = simple`
- Magazzino unico → aggiorna tutti gli SKU locali corrispondenti
- NON modifica no_backorder

---

# 5️⃣ ATTRIBUTES

## Service
`app/Services/Erp/AttributeSyncService.php`

## Fonte ERP
`dbo.RAGGRATTRIBCOMM_TOT`

| ERP | Laravel |
|------|----------|
| TABCODGRATTR_WEBT05 | attributes.code |
| NOMEATTR_WEBT05 | attribute_translations.label |
| ATTRIB_CODGRUAT_TOT | attribute_values.value_code |
| ATTRIB_DESCGRUAT_TOT | attribute_value_translations.label |
| ATTRIB_LINGUA_TOT | locale mapping |

## Tabelle Laravel
- attributes
- attribute_translations
- attribute_values
- attribute_value_translations

Gli attributi sono GLOBALI (`UNIQUE(code)`).

---

# 6️⃣ PRODUCT ATTRIBUTE VALUES

## Service
`app/Services/Erp/ProductAttributeValueSyncService.php`

## Fonte ERP

### `dbo.NOMIATTRIBUTI_WEBT05`
Slot → Attribute code mapping

### `dbo.ANAGRARTWEB_WEBT01`
Colonne:
```
GRUATTR01_W11 ... GRUATTR40_W50
```

## Tabella Laravel
`product_attribute_values`

Chiavi:
- product_id
- attribute_id
- attribute_value_id
- value_key

---

# 7️⃣ MEDIA

## Service
`app/Services/Erp/ProductMediaSyncService.php`

## Fonte ERP

### `dbo.ANAGRARTWEBFOTO_WEBT02`
- PATHFOTO_WEBT02 + FOTO01..FOTO10
- PATHFOTOATTR_WEBT02 + FOTOCOLORE01 (swatch)
- PATHICONE_WEBT02 + FOTOICONAxx
- DOCUMPDFxx
- DATAULTIMOAGG_WEBT02

### `dbo.ANAGRARTPADRE_TOT`
- PATHFOTOPADRE_WEBT00
- FOTOARTPADRE_WEBT00

## Tabella Laravel
`media_assets`

Campi principali:
- mediable_type
- mediable_id
- role (main, gallery, swatch, icon, pdf)
- filename
- erp_path
- local_path
- erp_lastchange

File copiati in:
```
storage/app/public/
```

---

# WATERMARK SUMMARY

| Modulo | Campo ERP watermark |
|--------|----------------------|
| Products Simple | MAX(ARTDESC_TOT.LASTCHANGE_WEBT87) |
| Products Parent | ANAGRARTPADRE_TOT.DATAULTIMOAGG_WEBT00 |
| Stock | MAGPROQTAUNICA.DATAULTVAR_MG70 |
| Media Simple | ANAGRARTWEBFOTO_WEBT02.DATAULTIMOAGG_WEBT02 |
| Media Parent | ANAGRARTPADRE_TOT.DATAULTIMOAGG_WEBT00 |
| Attributes | Full import |
| ProductAttributeValue | Full scan |

---

# NOTE IMPORTANTI

- Lo stock è gestito separatamente dal prodotto.
- no_backorder è gestito SOLO da ProductSyncService.
- Gli attributi sono globali.
- Il media sync può copiare fisicamente i file se abilitato.

---

Documento aggiornato all’architettura attuale del progetto Multistore.