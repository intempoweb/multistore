@extends('layouts.admin')

@section('title', 'Nuovo MediaKit')
@section('breadcrumb', 'MediaKit / Nuovo')

@section('content')
<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Creazione guidata</div>
        <h1 class="h3 mb-1">Nuovo MediaKit</h1>
        <div class="text-muted small">
            <strong>{{ $store->name }}</strong>
            <span class="mx-1">•</span>
            Ditta {{ $store->ditta_cg18 }}
            <span class="mx-1">•</span>
            Site {{ $store->erp_site_code }}
        </div>
    </div>

    <a href="{{ route('admin.mediakit.index') }}" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>
        Torna al MediaKit
    </a>
</div>

<div id="mediakit-alert" class="alert d-none" role="alert"></div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 text-center" id="mediakit-steps">
            @foreach([
                1 => ['Cliente', 'fa-user'],
                2 => ['Origine', 'fa-folder-open'],
                3 => ['Dati', 'fa-list-check'],
                4 => ['Conferma', 'fa-circle-check'],
            ] as $number => [$label, $icon])
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-3 h-100 {{ $number === 1 ? 'border-primary bg-primary-subtle' : 'bg-light' }}"
                         data-step-indicator="{{ $number }}">
                        <div class="fw-semibold">
                            <i class="fa-solid {{ $icon }} me-1"></i>
                            {{ $number }}. {{ $label }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<form id="mediakit-wizard-form"
      action="{{ route('admin.mediakit.store') }}"
      method="POST"
      enctype="multipart/form-data"
      novalidate>
    @csrf

    <input type="hidden" name="customer_id" id="customer_id">
    <input type="hidden" name="source_type" id="source_type">

    <section class="card border-0 shadow-sm" data-wizard-step="1">
        <div class="card-header bg-white border-0">
            <h2 class="h5 mb-1">Seleziona il cliente</h2>
            <div class="text-muted small">Cerca per ragione sociale, codice cliente, partita IVA, codice fiscale o email.</div>
        </div>

        <div class="card-body">
            <label for="customer-search" class="form-label">Ricerca cliente</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="search"
                       id="customer-search"
                       class="form-control"
                       placeholder="Digita almeno 2 caratteri"
                       autocomplete="off">
                <button type="button" class="btn btn-outline-secondary" id="customer-search-button">Cerca</button>
            </div>

            <div id="customer-search-status" class="text-muted small mt-2"></div>
            <div id="customer-results" class="list-group mt-3"></div>

            <div id="selected-customer" class="alert alert-success mt-3 d-none mb-0"></div>
        </div>

        <div class="card-footer bg-white d-flex justify-content-end">
            <button type="button" class="btn btn-primary" data-next disabled>
                Continua
                <i class="fa-solid fa-arrow-right ms-1"></i>
            </button>
        </div>
    </section>

    <section class="card border-0 shadow-sm d-none" data-wizard-step="2">
        <div class="card-header bg-white border-0">
            <h2 class="h5 mb-1">Scegli l’origine</h2>
            <div class="text-muted small">Indica da dove recuperare gli articoli del MediaKit.</div>
        </div>

        <div class="card-body">
            <div class="row g-3">
                @foreach([
                    'catalog' => ['Catalogo / SKU', 'Inserisci uno o più SKU completi. Non vengono effettuate ricerche parziali.', 'fa-boxes-stacked'],
                    'document' => ['Documento ERP', 'Recupera gli articoli da un documento ERP.', 'fa-file-invoice'],
                    'order' => ['Ordine esistente', 'Recupera gli articoli da un ordine del multistore.', 'fa-cart-shopping'],
                    'uploaded_ddt' => ['Upload DDT', 'Carica un file XLS, XLSX o CSV contenente gli SKU.', 'fa-file-arrow-up'],
                ] as $value => [$title, $description, $icon])
                    <div class="col-12 col-md-6">
                        <button type="button"
                                class="btn btn-outline-secondary text-start w-100 h-100 p-4"
                                data-source-option="{{ $value }}">
                            <div class="d-flex gap-3">
                                <div class="fs-3"><i class="fa-solid {{ $icon }}"></i></div>
                                <div>
                                    <div class="fw-bold mb-1">{{ $title }}</div>
                                    <div class="small">{{ $description }}</div>
                                </div>
                            </div>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card-footer bg-white d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" data-prev>
                <i class="fa-solid fa-arrow-left me-1"></i>
                Indietro
            </button>
            <button type="button" class="btn btn-primary" data-next disabled>
                Continua
                <i class="fa-solid fa-arrow-right ms-1"></i>
            </button>
        </div>
    </section>

    <section class="card border-0 shadow-sm d-none" data-wizard-step="3">
        <div class="card-header bg-white border-0">
            <h2 class="h5 mb-1">Dati origine</h2>
            <div class="text-muted small" id="source-details-subtitle"></div>
        </div>

        <div class="card-body">
            <div data-source-fields="catalog" class="d-none">
                <label for="product-search" class="form-label">Cerca per nome / codice articolo</label>
                <div class="input-group">
                    <input type="search"
                           id="product-search"
                           class="form-control"
                           placeholder="nome, SKU, linea, collezione, famiglia o attributo A12">
                    <button type="button" class="btn btn-primary px-5" id="product-search-button">
                        Cerca
                    </button>
                </div>

                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mt-3">
                    <div id="product-search-status" class="text-muted small">La ricerca usa il catalogo; i risultati mostrano e selezionano esclusivamente le varianti simple con i loro media.</div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge text-bg-primary" id="selected-product-count">0 selezionati</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="select-page-products">
                            Seleziona pagina
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-selected-products">
                            Deseleziona tutto
                        </button>
                    </div>
                </div>

                <div id="selected-products" class="mt-3"></div>
                <div id="product-id-inputs"></div>
                <textarea name="skus_text" id="skus_text" class="d-none"></textarea>

                <div id="product-grid"
                     class="row g-3 mt-1"
                     style="max-height: 760px; overflow-y: auto;"></div>

                <nav class="d-flex justify-content-center mt-4" aria-label="Paginazione prodotti">
                    <ul class="pagination mb-0" id="product-pagination"></ul>
                </nav>
            </div>

            <div data-source-fields="document" class="d-none">
                <label for="document-search" class="form-label">Documento del cliente</label>
                <div class="input-group">
                    <input type="search"
                           id="document-search"
                           class="form-control"
                           placeholder="Cerca numero o tipo documento oppure lascia vuoto per vedere gli ultimi 50">
                    <button type="button" class="btn btn-outline-secondary" id="document-search-button">
                        Cerca documenti
                    </button>
                </div>
                <div id="document-search-status" class="form-text"></div>
                <div id="document-results" class="list-group mt-3"></div>
                <div id="selected-document" class="alert alert-success d-none mt-3 mb-0"></div>
                <input type="hidden" id="document_reference">
            </div>

            <div data-source-fields="order" class="d-none">
                <label for="order-search" class="form-label">Ordine del cliente</label>
                <div class="input-group">
                    <input type="search"
                           id="order-search"
                           class="form-control"
                           placeholder="Cerca numero ordine oppure lascia vuoto">
                    <button type="button" class="btn btn-outline-secondary" id="order-search-button">
                        Cerca ordini
                    </button>
                </div>
                <div id="order-search-status" class="form-text"></div>
                <div id="order-results" class="list-group mt-3"></div>
                <div id="selected-order" class="alert alert-success d-none mt-3 mb-0"></div>
                <input type="hidden" id="order_reference">
            </div>

            <div data-source-fields="uploaded_ddt" class="d-none">
                <label for="ddt_file" class="form-label">File DDT</label>
                <input type="file"
                       name="ddt_file"
                       id="ddt_file"
                       class="form-control"
                       accept=".xls,.xlsx,.csv,.txt">
                <div class="form-text">
                    La prima riga deve contenere preferibilmente una colonna SKU oCODICE ARTICOLO.
                </div>
            </div>

            <input type="hidden" name="source_reference" id="source_reference">
        </div>

        <div class="card-footer bg-white d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" data-prev>
                <i class="fa-solid fa-arrow-left me-1"></i>
                Indietro
            </button>
            <button type="button" class="btn btn-primary" data-next>
                Riepilogo
                <i class="fa-solid fa-arrow-right ms-1"></i>
            </button>
        </div>
    </section>

    <section class="card border-0 shadow-sm d-none" data-wizard-step="4">
        <div class="card-header bg-white border-0">
            <h2 class="h5 mb-1">Conferma richiesta</h2>
            <div class="text-muted small">Controlla i dati prima della generazione.</div>
        </div>

        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Store</dt>
                <dd class="col-sm-9">{{ $store->name }}</dd>

                <dt class="col-sm-3">Cliente</dt>
                <dd class="col-sm-9" id="review-customer">—</dd>

                <dt class="col-sm-3">Origine</dt>
                <dd class="col-sm-9" id="review-source">—</dd>

                <dt class="col-sm-3">Riferimento</dt>
                <dd class="col-sm-9" id="review-reference">—</dd>
            </dl>

            <div id="preview-loading" class="text-center py-4 d-none">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="text-muted mt-2">Recupero e verifica prodotti…</div>
            </div>

            <div id="preview-result" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 mb-0">Prodotti trovati</h3>
                    <span class="badge text-bg-primary" id="preview-count">0</span>
                </div>
                <div id="preview-warnings"></div>
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 110px;">Immagini</th>
                                <th>SKU</th>
                                <th>Prodotto</th>
                                <th class="text-end">Media</th>
                            </tr>
                        </thead>
                        <tbody id="preview-products"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white d-flex justify-content-between align-items-center gap-3">
            <button type="button" class="btn btn-outline-secondary" data-prev>
                <i class="fa-solid fa-arrow-left me-1"></i>
                Indietro
            </button>

            <button type="submit" class="btn btn-primary" id="mediakit-submit">
                <i class="fa-solid fa-file-zipper me-1"></i>
                Genera MediaKit
            </button>
        </div>
    </section>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('mediakit-wizard-form');
    const customerSearchUrl = @json(route('admin.customers.index'));
    const productSearchUrl = @json(route('admin.mediakit.products'));
    const documentSearchUrl = @json(route('admin.mediakit.documents'));
    const orderSearchUrl = @json(route('admin.mediakit.orders'));
    const previewUrl = @json(route('admin.mediakit.preview'));
    const steps = [...document.querySelectorAll('[data-wizard-step]')];
    const indicators = [...document.querySelectorAll('[data-step-indicator]')];
    const alertBox = document.getElementById('mediakit-alert');
    const customerInput = document.getElementById('customer-search');
    const customerButton = document.getElementById('customer-search-button');
    const customerResults = document.getElementById('customer-results');
    const customerStatus = document.getElementById('customer-search-status');
    const selectedCustomerBox = document.getElementById('selected-customer');
    const customerIdInput = document.getElementById('customer_id');
    const sourceTypeInput = document.getElementById('source_type');
    const sourceReferenceInput = document.getElementById('source_reference');
    const submitButton = document.getElementById('mediakit-submit');

    let currentStep = 1;
    let selectedCustomer = null;
    let selectedSource = null;

    const sourceLabels = {
        catalog: 'Catalogo / SKU',
        document: 'Documento ERP',
        order: 'Ordine esistente',
        uploaded_ddt: 'Upload DDT',
    };

    function showAlert(message, type = 'danger') {
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearAlert() {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    }

    function goToStep(step) {
        currentStep = step;

        steps.forEach(section => {
            section.classList.toggle('d-none', Number(section.dataset.wizardStep) !== step);
        });

        indicators.forEach(indicator => {
            const number = Number(indicator.dataset.stepIndicator);
            indicator.classList.toggle('border-primary', number === step);
            indicator.classList.toggle('bg-primary-subtle', number === step);
            indicator.classList.toggle('bg-light', number !== step);
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(step) {
        clearAlert();

        if (step === 1 && !selectedCustomer) {
            showAlert('Seleziona un cliente.');
            return false;
        }

        if (step === 2 && !selectedSource) {
            showAlert('Seleziona l’origine del MediaKit.');
            return false;
        }

        if (step === 3) {
            if (selectedSource === 'catalog'
                && !document.querySelector('#product-id-inputs input')) {
                showAlert('Cerca e seleziona almeno un prodotto.');
                return false;
            }

            if (selectedSource === 'document' && !document.getElementById('document_reference').value.trim()) {
                showAlert('Inserisci il numero di registrazione del documento ERP.');
                return false;
            }

            if (selectedSource === 'order' && !document.getElementById('order_reference').value.trim()) {
                showAlert('Inserisci l’ID o il riferimento dell’ordine.');
                return false;
            }

            if (selectedSource === 'uploaded_ddt' && !document.getElementById('ddt_file').files.length) {
                showAlert('Seleziona il file DDT.');
                return false;
            }
        }

        return true;
    }

    function syncReference() {
        let reference = '';

        if (selectedSource === 'document') {
            reference = document.getElementById('document_reference').value.trim();
        } else if (selectedSource === 'order') {
            reference = document.getElementById('order_reference').value.trim();
        } else if (selectedSource === 'uploaded_ddt') {
            reference = document.getElementById('ddt_file').files[0]?.name || '';
        } else if (selectedSource === 'catalog') {
            const count = document.querySelectorAll('#product-id-inputs input').length;
            reference = `${count} prodotti`;
        }

        sourceReferenceInput.value = ['document', 'order'].includes(selectedSource) ? reference : '';
        document.getElementById('review-reference').textContent = reference || '—';
    }

    function updateReview() {
        syncReference();
        document.getElementById('review-customer').textContent = selectedCustomer
            ? `${selectedCustomer.name} — Cliente ${selectedCustomer.clifor}`
            : '—';
        document.getElementById('review-source').textContent = sourceLabels[selectedSource] || '—';
    }

    document.querySelectorAll('[data-next]').forEach(button => {
        button.addEventListener('click', async () => {
            if (!validateStep(currentStep)) {
                return;
            }

            if (currentStep === 3) {
                updateReview();
                goToStep(4);
                await loadPreview();
                return;
            }

            goToStep(Math.min(4, currentStep + 1));
        });
    });

    document.querySelectorAll('[data-prev]').forEach(button => {
        button.addEventListener('click', () => goToStep(Math.max(1, currentStep - 1)));
    });

    document.querySelectorAll('[data-source-option]').forEach(button => {
        button.addEventListener('click', () => {
            selectedSource = button.dataset.sourceOption;
            sourceTypeInput.value = selectedSource;

            document.querySelectorAll('[data-source-option]').forEach(option => {
                const active = option === button;
                option.classList.toggle('btn-primary', active);
                option.classList.toggle('btn-outline-secondary', !active);
            });

            document.querySelector('[data-wizard-step="2"] [data-next]').disabled = false;

            document.querySelectorAll('[data-source-fields]').forEach(group => {
                group.classList.toggle('d-none', group.dataset.sourceFields !== selectedSource);
            });

            document.getElementById('source-details-subtitle').textContent = sourceLabels[selectedSource];

            if (selectedSource === 'document' && selectedCustomer) {
                searchDocuments();
            }

            if (selectedSource === 'order' && selectedCustomer) {
                searchOrders();
            }
        });
    });

    async function searchCustomers() {
        const query = customerInput.value.trim();

        if (query.length < 2) {
            customerStatus.textContent = 'Digita almeno 2 caratteri.';
            customerResults.innerHTML = '';
            return;
        }

        customerButton.disabled = true;
        customerStatus.textContent = 'Ricerca in corso…';
        customerResults.innerHTML = '';

        try {
            const url = new URL(customerSearchUrl, window.location.origin);
            url.searchParams.set('search', query);
            url.searchParams.set('active', '1');

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Ricerca clienti non disponibile.');
            }

            const payload = await response.json();
            const customers = payload.data || [];

            customerStatus.textContent = customers.length
                ? `${customers.length} risultati visualizzati`
                : 'Nessun cliente trovato.';

            customers.slice(0, 20).forEach(customer => {
                const name = customer.ragsoanag_cg16 || customer.ragsocor_cg16 || `Cliente ${customer.clifor_cg44}`;
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold"></div>
                            <div class="small text-muted"></div>
                        </div>
                        <span class="badge text-bg-secondary align-self-start"></span>
                    </div>
                `;
                item.querySelector('.fw-semibold').textContent = name;
                item.querySelector('.small').textContent = [
                    customer.partiva_cg16 ? `P.IVA ${customer.partiva_cg16}` : null,
                    customer.indemail_cg16 || null,
                ].filter(Boolean).join(' • ');
                item.querySelector('.badge').textContent = `Cliente ${customer.clifor_cg44}`;

                item.addEventListener('click', () => {
                    selectedCustomer = {
                        id: customer.id,
                        name,
                        clifor: customer.clifor_cg44,
                        tipocf: customer.tipocf_cg44,
                    };
                    customerIdInput.value = customer.id;
                    selectedCustomerBox.innerHTML = `
                        <strong>Cliente selezionato:</strong>
                        <span></span>
                    `;
                    selectedCustomerBox.querySelector('span').textContent = `${name} — Cliente ${customer.clifor_cg44}`;
                    selectedCustomerBox.classList.remove('d-none');
                    customerResults.innerHTML = '';
                    customerStatus.textContent = '';
                    document.querySelector('[data-wizard-step="1"] [data-next]').disabled = false;
                });

                customerResults.appendChild(item);
            });
        } catch (error) {
            customerStatus.textContent = error.message || 'Errore durante la ricerca.';
        } finally {
            customerButton.disabled = false;
        }
    }

    customerButton.addEventListener('click', searchCustomers);
    customerInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchCustomers();
        }
    });



    const selectedProducts = new Map();
    let currentProductResults = [];
    let currentProductPage = 1;
    let currentProductLastPage = 1;

    function renderSelectedProducts() {
        const box = document.getElementById('selected-products');
        const inputs = document.getElementById('product-id-inputs');
        const counter = document.getElementById('selected-product-count');

        box.innerHTML = '';
        inputs.innerHTML = '';
        counter.textContent = `${selectedProducts.size} selezionati`;

        if (selectedProducts.size) {
            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex flex-wrap gap-2';

            selectedProducts.forEach(product => {
                const badge = document.createElement('button');
                badge.type = 'button';
                badge.className = 'btn btn-sm btn-primary';
                badge.innerHTML = '<span data-label></span> <i class="fa-solid fa-xmark ms-1"></i>';
                badge.querySelector('[data-label]').textContent = product.sku;
                badge.addEventListener('click', () => {
                    selectedProducts.delete(product.id);
                    renderSelectedProducts();
                    renderProductGrid();
                });
                wrapper.appendChild(badge);

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'product_ids[]';
                input.value = product.id;
                inputs.appendChild(input);
            });

            box.appendChild(wrapper);
        }
    }

    function renderProductGrid() {
        const grid = document.getElementById('product-grid');
        grid.innerHTML = '';

        if (!currentProductResults.length) {
            grid.innerHTML = '<div class="col-12"><div class="text-center text-muted py-5 border rounded">Nessun prodotto trovato.</div></div>';
            return;
        }

        currentProductResults.forEach(product => {
            const selected = selectedProducts.has(product.id);
            const column = document.createElement('div');
            column.className = 'col-12 col-sm-6 col-lg-4 col-xl-3';

            const card = document.createElement('div');
            card.className = `card h-100 shadow-sm product-card ${selected ? 'border-primary border-2' : ''}`;
            card.style.cursor = 'pointer';

            card.innerHTML = `
                <div class="position-relative bg-light" style="height: 210px;">
                    <img data-image class="w-100 h-100 p-3" style="object-fit: contain;" alt="">
                    <div class="position-absolute top-0 start-0 m-2">
                        <input class="form-check-input fs-5" type="checkbox" data-checkbox>
                    </div>
                    <span class="position-absolute top-0 end-0 m-2 badge text-bg-secondary" data-media></span>
                </div>
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">Variante</div>
                    <div class="fw-bold" data-sku></div>
                    <div class="small text-muted mt-1" data-name></div>
                    <div class="small text-muted mt-2" data-parent></div>
                </div>
            `;

            const image = card.querySelector('[data-image]');
            if (product.image_url) {
                image.src = product.image_url;
                image.alt = product.sku;
                image.addEventListener('error', () => {
                    image.removeAttribute('src');
                    image.alt = 'Immagine non disponibile';
                });
            } else {
                image.removeAttribute('src');
                image.alt = 'Immagine non disponibile';
            }

            card.querySelector('[data-checkbox]').checked = selected;
            card.querySelector('[data-sku]').textContent = product.sku;
            card.querySelector('[data-name]').textContent = product.name;
            card.querySelector('[data-parent]').textContent = product.parent_sku
                ? `Padre: ${product.parent_name || product.parent_sku} (${product.parent_sku})`
                : '';
            card.querySelector('[data-media]').textContent = `${product.media_count} media`;

            card.addEventListener('click', event => {
                if (event.target.closest('a')) return;

                if (selectedProducts.has(product.id)) {
                    selectedProducts.delete(product.id);
                } else {
                    selectedProducts.set(product.id, product);
                }

                renderSelectedProducts();
                renderProductGrid();
            });

            column.appendChild(card);
            grid.appendChild(column);
        });
    }

    function renderProductPagination(meta) {
        const pagination = document.getElementById('product-pagination');
        pagination.innerHTML = '';

        currentProductPage = Number(meta.current_page || 1);
        currentProductLastPage = Number(meta.last_page || 1);

        if (currentProductLastPage <= 1) {
            return;
        }

        const addButton = (label, page, disabled = false, active = false) => {
            const item = document.createElement('li');
            item.className = `page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}`;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'page-link';
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => searchProducts(page));

            item.appendChild(button);
            pagination.appendChild(item);
        };

        addButton('‹', currentProductPage - 1, currentProductPage <= 1);

        const start = Math.max(1, currentProductPage - 2);
        const finish = Math.min(currentProductLastPage, currentProductPage + 2);

        for (let page = start; page <= finish; page += 1) {
            addButton(String(page), page, false, page === currentProductPage);
        }

        addButton('›', currentProductPage + 1, currentProductPage >= currentProductLastPage);
    }

    async function searchProducts(page = 1) {
        if (!selectedCustomer) {
            showAlert('Seleziona prima il cliente.');
            return;
        }

        const search = document.getElementById('product-search').value.trim();
        const button = document.getElementById('product-search-button');
        const status = document.getElementById('product-search-status');

        if (search.length < 2) {
            status.textContent = 'Digita almeno 2 caratteri.';
            return;
        }

        button.disabled = true;
        status.textContent = 'Ricerca prodotti in corso…';

        try {
            const url = new URL(productSearchUrl, window.location.origin);
            url.searchParams.set('customer_id', selectedCustomer.id);
            url.searchParams.set('search', search);
            url.searchParams.set('page', page);

            const response = await fetch(url, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Ricerca prodotti non disponibile.');
            }

            currentProductResults = payload.data || [];

            status.textContent = payload.meta?.total
                ? `${payload.meta.total} prodotti trovati — pagina ${payload.meta.current_page} di ${payload.meta.last_page}`
                : 'Nessun prodotto trovato.';

            renderProductGrid();
            renderProductPagination(payload.meta || {});
        } catch (error) {
            status.textContent = error.message || 'Errore durante la ricerca prodotti.';
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('product-search-button').addEventListener('click', () => searchProducts(1));

    document.getElementById('product-search').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchProducts(1);
        }
    });

    document.getElementById('select-page-products').addEventListener('click', () => {
        currentProductResults.forEach(product => selectedProducts.set(product.id, product));
        renderSelectedProducts();
        renderProductGrid();
    });

    document.getElementById('clear-selected-products').addEventListener('click', () => {
        selectedProducts.clear();
        renderSelectedProducts();
        renderProductGrid();
    });

    async function searchDocuments() {
        if (!selectedCustomer) {
            showAlert('Seleziona prima il cliente.');
            return;
        }

        const button = document.getElementById('document-search-button');
        const status = document.getElementById('document-search-status');
        const results = document.getElementById('document-results');
        button.disabled = true;
        status.textContent = 'Ricerca documenti in corso…';
        results.innerHTML = '';

        try {
            const url = new URL(documentSearchUrl, window.location.origin);
            url.searchParams.set('customer_id', selectedCustomer.id);
            const search = document.getElementById('document-search').value.trim();
            if (search) url.searchParams.set('search', search);

            const response = await fetch(url, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Ricerca documenti non disponibile.');

            status.textContent = payload.data.length
                ? `${payload.data.length} documenti trovati`
                : 'Nessun documento trovato.';

            payload.data.forEach(documentRow => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `<div class="d-flex justify-content-between gap-3">
                    <div><div class="fw-semibold" data-title></div><div class="small text-muted" data-date></div></div>
                    <span class="badge text-bg-secondary" data-id></span>
                </div>`;
                item.querySelector('[data-title]').textContent = `${documentRow.type} ${documentRow.number}`;
                item.querySelector('[data-date]').textContent = documentRow.date || '';
                item.querySelector('[data-id]').textContent = `Reg. ${documentRow.id}`;

                item.addEventListener('click', () => {
                    clearAlert();
                    document.getElementById('document_reference').value = documentRow.id;
                    document.getElementById('selected-document').textContent =
                        `${documentRow.type} ${documentRow.number} del ${documentRow.date || '—'}`;
                    document.getElementById('selected-document').classList.remove('d-none');
                    results.innerHTML = '';
                    status.textContent = '';
                });

                results.appendChild(item);
            });
        } catch (error) {
            status.textContent = error.message || 'Errore durante la ricerca documenti.';
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('product-search-button').addEventListener('click', searchProducts);
    document.getElementById('product-search').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchProducts();
        }
    });

    document.getElementById('document-search-button').addEventListener('click', searchDocuments);
    document.getElementById('document-search').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchDocuments();
        }
    });

    async function searchOrders() {
        if (!selectedCustomer) {
            showAlert('Seleziona prima il cliente.');
            return;
        }

        const button = document.getElementById('order-search-button');
        const status = document.getElementById('order-search-status');
        const results = document.getElementById('order-results');
        button.disabled = true;
        status.textContent = 'Ricerca ordini in corso…';
        results.innerHTML = '';

        try {
            const url = new URL(orderSearchUrl, window.location.origin);
            url.searchParams.set('customer_id', selectedCustomer.id);
            const search = document.getElementById('order-search').value.trim();
            if (search) url.searchParams.set('search', search);

            const response = await fetch(url, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Ricerca ordini non disponibile.');

            status.textContent = payload.data.length ? `${payload.data.length} ordini trovati` : 'Nessun ordine trovato.';

            payload.data.forEach(order => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `<div class="d-flex justify-content-between gap-3">
                    <div><div class="fw-semibold"></div><div class="small text-muted"></div></div>
                    <span class="badge text-bg-secondary"></span>
                </div>`;
                item.querySelector('.fw-semibold').textContent = `Ordine ${order.number}`;
                item.querySelector('.small').textContent = [
                    order.placed_at,
                    order.total !== null ? `${order.total} ${order.currency}` : null
                ].filter(Boolean).join(' • ');
                item.querySelector('.badge').textContent = order.status || '—';

                item.addEventListener('click', () => {
                    document.getElementById('order_reference').value = order.id;
                    document.getElementById('selected-order').textContent = `Ordine ${order.number} selezionato`;
                    document.getElementById('selected-order').classList.remove('d-none');
                    results.innerHTML = '';
                    status.textContent = '';
                });

                results.appendChild(item);
            });
        } catch (error) {
            status.textContent = error.message || 'Errore durante la ricerca ordini.';
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('order-search-button').addEventListener('click', searchOrders);
    document.getElementById('order-search').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchOrders();
        }
    });

    async function loadPreview() {
        clearAlert();
        const loading = document.getElementById('preview-loading');
        const result = document.getElementById('preview-result');
        const productsBody = document.getElementById('preview-products');
        const warningsBox = document.getElementById('preview-warnings');
        const countBadge = document.getElementById('preview-count');

        loading.classList.remove('d-none');
        result.classList.add('d-none');
        productsBody.innerHTML = '';
        warningsBox.innerHTML = '';

        try {
            syncReference();
            const response = await fetch(previewUrl, {
                method: 'POST',
                body: new FormData(form),
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const payload = await response.json();

            if (!response.ok) {
                const errors = payload.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : (payload.message || 'Anteprima non disponibile.');
                throw new Error(errors);
            }

            countBadge.textContent = payload.data.count;

            (payload.data.warnings || []).forEach(warning => {
                const box = document.createElement('div');
                box.className = 'alert alert-warning py-2';
                box.textContent = warning;
                warningsBox.appendChild(box);
            });

            payload.data.products.forEach(product => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><div class="d-flex flex-wrap gap-1" data-images></div></td>
                    <td><code></code></td>
                    <td data-name></td>
                    <td class="text-end" data-count></td>
                `;

                row.querySelector('code').textContent = product.sku;
                row.querySelector('[data-name]').textContent = product.name;
                row.querySelector('[data-count]').textContent =
                    product.media_count === null ? '—' : product.media_count;

                const imagesBox = row.querySelector('[data-images]');
                const images = Array.isArray(product.images) ? product.images : [];

                if (!images.length) {
                    const empty = document.createElement('span');
                    empty.className = 'text-muted small';
                    empty.textContent = 'Nessuna';
                    imagesBox.appendChild(empty);
                } else {
                    images.slice(0, 4).forEach(image => {
                        const link = document.createElement('a');
                        link.href = image.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.title = image.filename || product.sku;

                        const img = document.createElement('img');
                        img.src = image.url;
                        img.alt = image.filename || product.sku;
                        img.loading = 'lazy';
                        img.className = 'rounded border bg-white';
                        img.style.width = '44px';
                        img.style.height = '44px';
                        img.style.objectFit = 'contain';

                        img.addEventListener('error', () => {
                            link.remove();
                            if (!imagesBox.children.length) {
                                const unavailable = document.createElement('span');
                                unavailable.className = 'text-danger small';
                                unavailable.textContent = 'Non caricabile';
                                imagesBox.appendChild(unavailable);
                            }
                        });

                        link.appendChild(img);
                        imagesBox.appendChild(link);
                    });
                }

                productsBody.appendChild(row);
            });

            if (!payload.data.products.length) {
                productsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Nessun prodotto disponibile.</td></tr>';
                submitButton.disabled = true;
            } else {
                submitButton.disabled = false;
            }

            result.classList.remove('d-none');
        } catch (error) {
            showAlert(error.message || 'Errore durante il recupero dell’anteprima.');
            goToStep(3);
        } finally {
            loading.classList.add('d-none');
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (!validateStep(3)) {
            goToStep(3);
            return;
        }

        clearAlert();
        syncReference();

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Creazione in corso…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json();

            if (!response.ok) {
                const errors = payload.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : (payload.message || 'Impossibile creare il MediaKit.');
                throw new Error(errors);
            }

            showAlert(payload.message || 'Richiesta MediaKit creata.', 'success');

            setTimeout(() => {
                window.location.href = payload.redirect_url || @json(route('admin.mediakit.index'));
            }, 700);
        } catch (error) {
            showAlert(error.message || 'Errore durante la creazione del MediaKit.');
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fa-solid fa-file-zipper me-1"></i>Genera MediaKit';
        }
    });
});
</script>
@endpush
