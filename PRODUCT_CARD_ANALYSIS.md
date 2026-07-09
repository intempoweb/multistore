# Product Card & Color/Format Selection Analysis

## Overview
This document provides a complete analysis of the product card template, CSS styling, and JavaScript handling for color/format selection in the CIAK B2C storefront.

---

## 1. HTML STRUCTURE - Product Card Template

**File:** [resources/views/storefront/base/partials/product-card.blade.php](resources/views/storefront/base/partials/product-card.blade.php)

### Main Card Container
```html
<div class="card h-100 border-0 shadow-sm product-listing-card"
    data-product-card
    data-product-sku="{{ $card->targetSku }}">
```

### Color Options Section (Lines 132-160)
```html
@if($card->colorOptions->isNotEmpty())
    <div class="mb-2">
        <div class="small text-muted mb-1">{{ __('themes_b2c.product.color') }}</div>

        <div class="d-flex flex-wrap gap-2">
            @foreach($card->colorOptions as $option)
                @php($payload = $card->colorOptionPayload($option))

                <button
                    type="button"
                    class="border-0 bg-transparent p-0 {{ $payload['is_selected'] ? 'is-active is-selected' : '' }}"
                    data-product-card-variant
                    data-variant-type="color"
                    data-variant-sku="{{ $payload['sku'] }}"
                    data-variant-url="{{ $contextUrl($payload['url'] ?? null) }}"
                    data-variant-image="{{ $payload['image'] }}"
                    data-variant-hover-image="{{ $payload['hover_image'] }}"
                    data-variant-price="{{ $payload['price'] }}"
                    data-variant-qty-min="{{ $payload['quantity_min'] }}"
                    data-variant-qty-step="{{ $payload['quantity_step'] }}"
                    data-variant-pack-multiple="{{ $payload['pack_multiple'] }}"
                    data-variant-purchasable="{{ $payload['is_purchasable'] ? '1' : '0' }}"
                    title="{{ $payload['value'] ?? '' }}"
                    aria-label="{{ __('themes_b2c.product.color') }} {{ $payload['value'] ?? '-' }}"
                    aria-pressed="{{ $payload['is_selected'] ? 'true' : 'false' }}"
                >
                    @if(!empty($payload['swatch_url']))
                        <span class="product-listing-option-swatch d-inline-flex border rounded-circle overflow-hidden {{ $payload['is_selected'] ? 'is-active is-selected' : '' }}">
                            <img src="{{ $payload['swatch_url'] }}" alt="{{ $payload['value'] ?? '' }}">
                        </span>
                    @else
                        <span class="product-listing-option-swatch badge text-bg-light border {{ $payload['is_selected'] ? 'is-active is-selected' : '' }}">
                            {{ $payload['value'] ?? '-' }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
@endif
```

### Format Options Section (Lines 162-189)
```html
@if($card->formatOptions->isNotEmpty())
    <div class="mb-2">
        <div class="small text-muted mb-1">{{ __('themes_b2c.product.format') }}</div>

        <div class="d-flex flex-wrap gap-2">
            @foreach($card->formatOptions as $option)
                @php($payload = $card->formatOptionPayload($option))

                <button
                    type="button"
                    class="badge rounded-pill text-bg-light border product-listing-option-pill {{ $payload['is_selected'] ? 'is-active is-selected' : '' }}"
                    data-product-card-variant
                    data-variant-type="format"
                    data-variant-sku="{{ $payload['sku'] }}"
                    data-variant-url="{{ $contextUrl($payload['url'] ?? null) }}"
                    data-variant-image="{{ $payload['image'] }}"
                    data-variant-hover-image="{{ $payload['hover_image'] }}"
                    data-variant-price="{{ $payload['price'] }}"
                    data-variant-qty-min="{{ $payload['quantity_min'] }}"
                    data-variant-qty-step="{{ $payload['quantity_step'] }}"
                    data-variant-pack-multiple="{{ $payload['pack_multiple'] }}"
                    data-variant-purchasable="{{ $payload['is_purchasable'] ? '1' : '0' }}"
                    aria-pressed="{{ $payload['is_selected'] ? 'true' : 'false' }}"
                >
                    {{ $payload['value'] ?? '-' }}
                </button>
            @endforeach
        </div>
    </div>
@endif
```

### Price Display Location (Lines 191-204)
```html
<div class="mb-3 mt-2">
    <div class="fw-semibold" data-product-card-price>
        {{ $card->formattedPrice() }}
    </div>

    @if($isB2bStore && $card->hasVariablePrice)
        <div class="small text-muted">
            {{ __('Prezzo variabile in base alla quantità') }}
        </div>
    @endif

    <div
        class="small text-danger mt-1 {{ $card->isPurchasable ? 'd-none' : '' }}"
        data-product-card-unavailable
    >
        {{ __('themes_b2c.product.out_of_stock') }}
    </div>
</div>
```

**Key Element:** `data-product-card-price` - This is the price container that should be updated when a variant is selected.

---

## 2. CSS STYLING

### Color Swatch Styles
**File:** [public/css/app.css](public/css/app.css) (Lines 1260-1280)

```css
.product-listing-option-swatch {
    width: 24px;
    height: 24px;
    color: #24201e;
    background: #fff;
    border-color: #d6d0cb !important;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease, color .15s ease;
}

.product-listing-option-swatch img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-listing-option-swatch:hover,
.product-listing-option-swatch:focus,
.product-listing-option-swatch.is-active,
.product-listing-option-swatch.is-selected {
    border-color: #24201e !important;
    box-shadow: 0 0 0 3px #fff, 0 0 0 4px #24201e;
}
```

**ISSUE IDENTIFIED:** The `:focus` pseudo-class applies the same styling as `.is-selected`. This includes:
- `border-color: #24201e`
- `box-shadow: 0 0 0 3px #fff, 0 0 0 4px #24201e` (This creates the blue-looking box shadow on click!)

### Format Pill Styles
**File:** [public/css/app.css](public/css/app.css) (Lines 1284-1296)

```css
.product-listing-option-pill {
    color: #24201e !important;
    border-color: #d6d0cb !important;
    background: #fff !important;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease, color .15s ease;
}

.product-listing-option-pill:hover,
.product-listing-option-pill:focus,
.product-listing-option-pill.is-active,
.product-listing-option-pill.is-selected {
    color: #fff !important;
    border-color: #24201e !important;
    background: #24201e !important;
}
```

**ISSUE IDENTIFIED:** The `:focus` pseudo-class applies hover-like styling. When the button receives focus after click, it stays highlighted with dark background, which might make the price invisible if it's positioned near the button area.

### CIAK Theme Override
**File:** [public/css/themes/b2c/ciak.css](public/css/themes/b2c/ciak.css) (Line 884)

```css
.ciak-site .product-listing-option-swatch { width: 20px; height: 20px; }
```

This only overrides the width/height. No additional focus styles are defined here.

### Bootstrap Button Focus
**File:** [public/css/themes/b2c/ciak.css](public/css/themes/b2c/ciak.css) (Lines 30-40)

```css
.ciak-site .btn-primary {
    --bs-btn-bg: #111;
    color: #fff;
    --bs-btn-border-color: #111;
    --bs-btn-hover-bg: var(--ciak-success);
    --bs-btn-hover-border-color: var(--ciak-success);
    --bs-btn-active-bg: var(--ciak-success);
    --bs-btn-active-border-color: var(--ciak-success);
    --bs-btn-focus-shadow-rgb: 17, 17, 17;
}
```

The `--bs-btn-focus-shadow-rgb` is Bootstrap's CSS variable for button focus shadow color.

---

## 3. JAVASCRIPT EVENT HANDLING

### Variant Selection Logic
**File:** [public/js/product-card.js](public/js/product-card.js) (Lines 350-410)

```javascript
variants.forEach(function (button) {
    button.addEventListener('click', function () {
        const variantSku = button.dataset.variantSku;

        if (!variantSku) return;

        const variantType = button.dataset.variantType || '';
        const variantUrl = button.dataset.variantUrl || '';
        const variantImage = button.datasetVariantImage || button.dataset.variantImage || '';
        const variantHoverImage = button.dataset.variantHoverImage || '';
        const variantPrice = button.dataset.variantPrice || '';
        const variantBarcode = button.dataset.variantBarcode || '';
        const variantPurchasable = button.dataset.variantPurchasable !== '0';

        // Remove active state from buttons of the same type
        variants.forEach(function (item) {
            if ((item.dataset.variantType || '') !== variantType) return;

            item.classList.remove('is-active', 'is-selected');
            item.setAttribute('aria-pressed', 'false');

            item.querySelector('.product-listing-option-swatch')?.classList.remove('is-active', 'is-selected');
            item.querySelector('.product-listing-option-pill')?.classList.remove('is-active', 'is-selected');
        });

        // Add active state to clicked button
        button.classList.add('is-active', 'is-selected');
        button.setAttribute('aria-pressed', 'true');

        button.querySelector('.product-listing-option-swatch')?.classList.add('is-active', 'is-selected');
        button.querySelector('.product-listing-option-pill')?.classList.add('is-active', 'is-selected');

        // Update SKU
        if (skuInput) {
            skuInput.value = variantSku;
        }

        if (skuLabel) {
            skuLabel.textContent = variantSku;
        }

        // Update Price (KEY LINE)
        if (priceNode) {
            priceNode.textContent = variantPrice !== '' ? formatPrice(variantPrice) : '—';
        }

        // Update Purchasable State
        setPurchasableState(variantPurchasable);
    });
});
```

**KEY OBSERVATIONS:**
1. When a variant button is clicked, the `is-active` and `is-selected` classes are added to the button
2. The price is updated via: `priceNode.textContent = variantPrice !== '' ? formatPrice(variantPrice) : '—';`
3. The price node is selected via: `priceNode = card.querySelector('[data-product-card-price]');`

### Price Update Initialization
The code correctly reads the price from the `data-variant-price` attribute:
```javascript
const variantPrice = button.dataset.variantPrice || '';
```

And formats it using:
```javascript
function formatPrice(value) {
    if (value === '' || value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }

    return '€ ' + Number(value).toLocaleString('it-IT', {
        minimumFractionDigits: isB2B ? 3 : 2,
        maximumFractionDigits: isB2B ? 3 : 2
    });
}
```

---

## 4. FOCUS/OUTLINE STYLES - THE BLUE BORDER

### The Root Cause
The blue focus outline you're seeing is likely from:

1. **CSS `:focus` pseudo-class on `.product-listing-option-swatch`:**
   ```css
   .product-listing-option-swatch:focus {
       border-color: #24201e !important;
       box-shadow: 0 0 0 3px #fff, 0 0 0 4px #24201e;
   }
   ```
   This creates a thick border effect that may appear blue depending on browser rendering.

2. **Default browser focus outline** on `<button>` elements
   - Browsers apply an outline by default for accessibility
   - This can appear as a blue box shadow on some browsers

3. **Bootstrap's `--bs-btn-focus-shadow-rgb` variable**
   - Set to `17, 17, 17` (nearly black), but this might interact with system colors

### Why Price Might Disappear
The price disappearance could be caused by:

1. **Focus state triggering overflow** - If the button's focus shadow expands beyond its container
2. **Layout shift** - The box-shadow might cause a shift in the card layout
3. **Container overflow hidden** - If the price container or parent has `overflow: hidden`
4. **Z-index layering** - The focused button's shadow might overlay the price text
5. **Button receiving focus** - Buttons are tabbable by default, and clicking them triggers focus

---

## 5. DATA ATTRIBUTES - VARIANT PAYLOAD

The variant buttons carry all necessary data in attributes:

```html
data-variant-sku="{{ $payload['sku'] }}"
data-variant-price="{{ $payload['price'] }}"
data-variant-image="{{ $payload['image'] }}"
data-variant-hover-image="{{ $payload['hover_image'] }}"
data-variant-qty-min="{{ $payload['quantity_min'] }}"
data-variant-qty-step="{{ $payload['quantity_step'] }}"
data-variant-pack-multiple="{{ $payload['pack_multiple'] }}"
data-variant-purchasable="{{ $payload['is_purchasable'] ? '1' : '0' }}"
data-variant-url="{{ $contextUrl($payload['url'] ?? null) }}"
data-variant-type="color"  <!-- or "format" -->
```

---

## 6. LAYOUT STRUCTURE

### Card Body Flow
```
Card Container (h-100, flex column)
├── Product Image Section
├── SKU Label (small text)
├── Product Title
├── Variant Count (if multiple)
├── Color Options Section (mb-2)
│   ├── Label
│   └── Button Flex Container (gap-2)
├── Format Options Section (mb-2)
│   ├── Label
│   └── Button Flex Container (gap-2)
├── PRICE SECTION (mb-3, mt-2) ⬅️ TARGET ELEMENT
│   ├── fw-semibold [data-product-card-price]
│   ├── Variable price note (B2B only)
│   └── Out of stock message
├── Add to Cart Form (mt-auto)
└── Feedback Message (d-none)
```

**The price is positioned AFTER the color/format sections**, so any overflow from those buttons could visually obscure it.

---

## 7. SUMMARY TABLE

| Component | Location | Issue |
|-----------|----------|-------|
| HTML Template | [resources/views/storefront/base/partials/product-card.blade.php](resources/views/storefront/base/partials/product-card.blade.php) | Color/Format buttons store price in `data-variant-price` |
| Color Swatches CSS | [public/css/app.css](public/css/app.css#L1260-L1280) | `:focus` applies box-shadow: 0 0 0 3px #fff, 0 0 0 4px #24201e |
| Format Pills CSS | [public/css/app.css](public/css/app.css#L1284-L1296) | `:focus` applies dark background color |
| Variant Logic JS | [public/js/product-card.js](public/js/product-card.js#L350-L410) | Correctly updates priceNode with variantPrice |
| Price Element | HTML line 195 | `<div class="fw-semibold" data-product-card-price>` |
| Focus Ring | CSS app.css | `.product-listing-option-swatch:focus` box-shadow |

---

## Recommendations to Fix

### 1. Remove Focus Shadow on Variant Buttons
```css
.product-listing-option-swatch:focus {
    outline: none;
    box-shadow: none;
}

.product-listing-option-pill:focus {
    outline: none;
}
```

### 2. Use :focus-visible Instead
```css
.product-listing-option-swatch:focus-visible {
    /* accessibility-safe focus indicator */
    outline: 2px solid #24201e;
    outline-offset: 2px;
}
```

### 3. Prevent Button From Receiving Focus After Click
```javascript
button.addEventListener('click', function () {
    // ... variant logic ...
    button.blur(); // Remove focus after click
});
```

### 4. Add Pointer-Events None to Focus Shadow
Prevent the focus state from affecting layout or visibility.

