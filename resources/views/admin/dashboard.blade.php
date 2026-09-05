@extends('layouts.admin')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
@php
    $priceMin = $stats['price_min'] ?? null;
    $priceMax = $stats['price_max'] ?? null;
    $adminUser = auth()->user();
    $canAdmin = fn (string $section): bool => $adminUser
        && method_exists($adminUser, 'canAccessAdminSection')
        && $adminUser->canAccessAdminSection($section);

    $fmtNumber = fn (mixed $value, int $decimals = 0): string => number_format((float) $value, $decimals, ',', '.');
    $fmtMoney = fn (mixed $value): string => '€ ' . number_format((float) $value, 2, ',', '.');
    $fmtKpi = function (array $kpi) use ($fmtMoney, $fmtNumber): string {
        return match ($kpi['format'] ?? 'integer') {
            'currency' => $fmtMoney($kpi['value'] ?? 0),
            'decimal' => $fmtNumber($kpi['value'] ?? 0, 1),
            default => $fmtNumber($kpi['value'] ?? 0),
        };
    };
    $trendClass = function (?float $variation): string {
        if ($variation === null) {
            return 'text-muted';
        }

        return $variation >= 0 ? 'text-success' : 'text-danger';
    };
    $trendIcon = fn (?float $variation): string => $variation === null
        ? 'fa-minus'
        : ($variation >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down');
    $trendLabel = function (?float $variation): string {
        if ($variation === null) {
            return 'N/D periodo precedente';
        }

        return number_format(abs($variation), 1, ',', '.') . '% periodo precedente';
    };

    $kpis = $analytics['kpis'] ?? [];
    $salesChart = $analytics['sales_chart'] ?? [];
    $topProducts = $analytics['top_products'] ?? [];
    $orderStatuses = $analytics['order_statuses'] ?? [];
    $paymentMethods = $analytics['payment_methods'] ?? [];
@endphp

<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Analytics e reporting e-commerce</div>
        <h1 class="h3 mb-1">Dashboard amministrativa</h1>
        <div class="text-muted small">
            <strong>{{ $store->name }}</strong>
            <span class="mx-1">•</span>
            Ditta {{ $store->ditta_cg18 }}
            <span class="mx-1">•</span>
            Site {{ $store->erp_site_code }}
            <span class="mx-1">•</span>
            {{ $store->domain }}
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @if($canAdmin('orders') && Route::has('admin.orders.index'))
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-primary">
                <i class="fa-solid fa-receipt me-1"></i>
                Ordini
            </a>
        @endif

        @if($canAdmin('commercial') && Route::has('admin.customers.index'))
            <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-dark">
                <i class="fa-solid fa-users me-1"></i>
                Clienti
            </a>
        @endif

        @if($canAdmin('super'))
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-box me-1"></i>
                Prodotti
            </a>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.dashboard') }}" class="row g-3 align-items-end">
            <div class="col-12 col-lg-3">
                <label class="form-label">Periodo</label>
                <select name="period" class="form-select" data-dashboard-period>
                    @foreach($periodPresets as $value => $label)
                        <option value="{{ $value }}" @selected($dateRange->preset === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2" data-custom-period-field>
                <label class="form-label">Dal</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from', $dateRange->start->toDateString()) }}">
            </div>

            <div class="col-6 col-lg-2" data-custom-period-field>
                <label class="form-label">Al</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to', $dateRange->end->toDateString()) }}">
            </div>

            <div class="col-12 col-lg-auto">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-filter me-1"></i>
                    Applica
                </button>
            </div>

            <div class="col-12 col-xl">
                <div class="d-flex flex-wrap justify-content-xl-end gap-2">
                    <span class="badge rounded-pill text-bg-light border px-3 py-2">
                        <i class="fa-solid fa-store me-1"></i>
                        Store: {{ $store->name }}
                    </span>
                    <span class="badge rounded-pill text-bg-light border px-3 py-2">
                        <i class="fa-regular fa-calendar me-1"></i>
                        {{ $analytics['range']['label'] ?? $dateRange->label() }}
                    </span>
                    <span class="badge rounded-pill text-bg-warning border px-3 py-2">
                        GA4 non configurato
                    </span>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['key' => 'revenue', 'label' => 'Fatturato', 'icon' => 'fa-euro-sign', 'class' => 'primary'],
        ['key' => 'orders', 'label' => 'Ordini', 'icon' => 'fa-receipt', 'class' => 'success'],
        ['key' => 'aov', 'label' => 'AOV', 'icon' => 'fa-chart-line', 'class' => 'info'],
        ['key' => 'quantity_sold', 'label' => 'Prodotti venduti', 'icon' => 'fa-boxes-stacked', 'class' => 'warning'],
        ['key' => 'customers', 'label' => 'Clienti', 'icon' => 'fa-users', 'class' => 'dark'],
        ['key' => 'new_customers', 'label' => 'Nuovi clienti', 'icon' => 'fa-user-plus', 'class' => 'success'],
        ['key' => 'returning_customers', 'label' => 'Clienti ricorrenti', 'icon' => 'fa-rotate-left', 'class' => 'secondary'],
        ['key' => 'paid_orders', 'label' => 'Ordini pagati', 'icon' => 'fa-credit-card', 'class' => 'primary'],
    ] as $card)
        @php
            $kpi = $kpis[$card['key']] ?? ['value' => 0, 'variation' => null, 'format' => 'integer'];
            $variation = $kpi['variation'] ?? null;
        @endphp
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 admin-kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="text-muted small text-uppercase mb-1">{{ $card['label'] }}</div>
                            <div class="fs-3 fw-bold">{{ $fmtKpi($kpi) }}</div>
                        </div>
                        <div class="admin-kpi-icon text-bg-{{ $card['class'] }}">
                            <i class="fa-solid {{ $card['icon'] }}"></i>
                        </div>
                    </div>
                    <div class="small mt-3 {{ $trendClass($variation) }}">
                        <i class="fa-solid {{ $trendIcon($variation) }} me-1"></i>
                        {{ $trendLabel($variation) }}
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    <h2 class="h5 mb-1">Andamento vendite</h2>
                    <div class="text-muted small">Fatturato, ordini e valore medio nel periodo selezionato</div>
                </div>
                <span class="badge rounded-pill text-bg-light border align-self-lg-start px-3 py-2">
                    Granularita: {{ $analytics['range']['granularity'] ?? 'day' }}
                </span>
            </div>
            <div class="card-body">
                <div class="admin-chart-box">
                    <canvas
                        id="salesTrendChart"
                        data-chart='@json($salesChart)'
                        aria-label="Andamento vendite"
                    ></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 mb-1">Ordini per stato</h2>
                <div class="text-muted small">Distribuzione nel periodo selezionato</div>
            </div>
            <div class="card-body">
                @forelse($orderStatuses as $status)
                    @php
                        $maxStatus = max(1, collect($orderStatuses)->max('total') ?? 1);
                        $width = min(100, ((int) $status['total'] / $maxStatus) * 100);
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">{{ \App\Models\Order::orderStatusLabels()[$status['label']] ?? strtoupper($status['label']) }}</span>
                            <span>{{ number_format((int) $status['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="progress admin-progress">
                            <div class="progress-bar" style="width: {{ $width }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Nessun ordine nel periodo selezionato.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    <h2 class="h5 mb-1">Top prodotti</h2>
                    <div class="text-muted small">Classifica ordinata per fatturato generato</div>
                </div>
                @if($canAdmin('super') && Route::has('admin.products.index'))
                    <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-outline-secondary align-self-lg-start">
                        <i class="fa-solid fa-box me-1"></i>
                        Catalogo
                    </a>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Prodotto</th>
                            <th>SKU</th>
                            <th class="text-end">Qta</th>
                            <th class="text-end">Ordini</th>
                            <th class="text-end">Fatturato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProducts as $product)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $product['product_name'] }}</div>
                                    <div class="text-muted small">
                                        @if($product['family'])
                                            Fam. {{ $product['family'] }}
                                        @endif
                                        @if($product['group'])
                                            <span class="mx-1">•</span> Gruppo {{ $product['group'] }}
                                        @endif
                                        @if($product['brand'])
                                            <span class="mx-1">•</span> Brand {{ $product['brand'] }}
                                        @endif
                                    </div>
                                </td>
                                <td><code>{{ $product['sku'] }}</code></td>
                                <td class="text-end">{{ $fmtNumber($product['quantity'], 1) }}</td>
                                <td class="text-end">{{ $fmtNumber($product['orders']) }}</td>
                                <td class="text-end fw-semibold">{{ $fmtMoney($product['revenue']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted">Nessun prodotto venduto nel periodo selezionato.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 mb-1">Metodi di pagamento</h2>
                <div class="text-muted small">Ordini raggruppati per gateway/metodo</div>
            </div>
            <div class="card-body">
                @forelse($paymentMethods as $method)
                    @php
                        $maxPayment = max(1, collect($paymentMethods)->max('total') ?? 1);
                        $width = min(100, ((int) $method['total'] / $maxPayment) * 100);
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">{{ strtoupper($method['label']) }}</span>
                            <span>{{ number_format((int) $method['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="progress admin-progress">
                            <div class="progress-bar bg-dark" style="width: {{ $width }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Nessun pagamento nel periodo selezionato.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 mb-1">Accesso rapido</h2>
                <div class="text-muted small">Aree principali dello store attivo</div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    @foreach([
                        ['section' => 'orders', 'route' => 'admin.orders.index', 'icon' => 'fa-receipt', 'label' => 'Ordini', 'text' => 'Ordini B2B/B2C, pagamenti e spedizioni'],
                        ['section' => 'commercial', 'route' => 'admin.customers.index', 'icon' => 'fa-users', 'label' => 'Clienti', 'text' => 'Anagrafiche clienti, ACL e listini associati'],
                        ['section' => 'super', 'route' => 'admin.products.index', 'icon' => 'fa-box', 'label' => 'Prodotti', 'text' => 'Prodotti, immagini, attributi, prezzi e listini'],
                        ['section' => 'super', 'route' => 'admin.catalog.index', 'icon' => 'fa-sitemap', 'label' => 'Catalogo', 'text' => 'Categorie ERP e prodotti assegnati ai nodi'],
                        ['section' => 'super', 'route' => 'admin.promotions.index', 'icon' => 'fa-percent', 'label' => 'Promozioni', 'text' => 'Sconti automatici, soglie carrello e regole promo'],
                        ['section' => 'super', 'route' => 'admin.erp-sync.index', 'icon' => 'fa-rotate', 'label' => 'ERP Sync', 'text' => 'Dry run e sincronizzazioni ERP'],
                    ] as $link)
                        @if($canAdmin($link['section']) && Route::has($link['route']))
                            <div class="col-12 col-md-6">
                                <a href="{{ route($link['route']) }}" class="card border h-100 text-decoration-none text-reset admin-quick-link">
                                    <div class="card-body d-flex align-items-center gap-3">
                                        <div class="admin-quick-link-icon">
                                            <i class="fa-solid {{ $link['icon'] }}"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $link['label'] }}</div>
                                            <div class="small text-muted">{{ $link['text'] }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 mb-1">Catalogo store</h2>
                <div class="text-muted small">Riepilogo del contesto amministrato</div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted small">Prodotti</div>
                        <div class="fw-semibold fs-5">{{ $fmtNumber($stats['products_total'] ?? 0) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Attivi</div>
                        <div class="fw-semibold fs-5">{{ $fmtNumber($stats['products_active'] ?? 0) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Famiglie</div>
                        <div class="fw-semibold fs-5">{{ $fmtNumber($stats['families_total'] ?? 0) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Gruppi</div>
                        <div class="fw-semibold fs-5">{{ $fmtNumber($stats['groups_total'] ?? 0) }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Prezzi</div>
                        <div class="fw-semibold">
                            @if($priceMin !== null || $priceMax !== null)
                                {{ $priceMin !== null ? $fmtMoney($priceMin) : 'N/D' }}
                                <span class="mx-1">-</span>
                                {{ $priceMax !== null ? $fmtMoney($priceMax) : 'N/D' }}
                            @else
                                N/D
                            @endif
                        </div>
                        <div class="text-muted small mt-1">
                            Con prezzo: {{ $fmtNumber($stats['products_with_price'] ?? 0) }}
                            <span class="mx-1">•</span>
                            Senza prezzo: {{ $fmtNumber($stats['products_without_price'] ?? 0) }}
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Attributi globali</div>
                        <div class="fw-semibold">
                            {{ $fmtNumber($stats['attributes_total'] ?? 0) }}
                            <span class="mx-1">•</span>
                            valori {{ $fmtNumber($stats['attribute_values_total'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/admin/analytics-dashboard.js') }}" defer></script>
@endpush
