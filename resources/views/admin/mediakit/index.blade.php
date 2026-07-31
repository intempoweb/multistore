@extends('layouts.admin')

@section('title', 'MediaKit')
@section('breadcrumb', 'MediaKit')

@section('content')
<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Archivio immagini e documenti dello store corrente</div>
        <h1 class="h3 mb-1">MediaKit</h1>
        <div class="text-muted small d-flex flex-wrap gap-2 align-items-center">
            <span><strong>{{ $store->name }}</strong></span>
            <span>•</span>
            <span>Ditta {{ $store->ditta_cg18 }}</span>
            <span>•</span>
            <span>Site {{ $store->erp_site_code }}</span>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.mediakit.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>
            Nuovo MediaKit
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div>
            <h2 class="h5 mb-1">Richieste MediaKit</h2>
            <div class="text-muted small">
                Elenco delle richieste generate per lo store selezionato.
            </div>
        </div>
        <span class="badge text-bg-secondary">
            {{ number_format($requests->total(), 0, ',', '.') }} richieste
        </span>
    </div>

    <div class="card-body p-0">
        @if($requests->isEmpty())
            <div class="p-5 text-center">
                <div class="fs-1 text-muted mb-3">
                    <i class="fa-solid fa-photo-film"></i>
                </div>
                <h3 class="h5">Nessun MediaKit presente</h3>
                <p class="text-muted mb-3">
                    Crea la prima richiesta MediaKit per questo store.
                </p>
                <a href="{{ route('admin.mediakit.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-1"></i>
                    Nuovo MediaKit
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Origine</th>
                            <th>Stato</th>
                            <th>Avanzamento</th>
                            <th>Creato</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $mediaKitRequest)
                            <tr
                                data-mediakit-row
                                data-status="{{ $mediaKitRequest->status }}"
                                data-status-url="{{ route('admin.mediakit.show', $mediaKitRequest) }}"
                                data-download-url="{{ route('admin.mediakit.download', $mediaKitRequest) }}"
                                data-send-email-url="{{ route('admin.mediakit.send-email', $mediaKitRequest) }}"
                                data-default-email="{{ data_get($mediaKitRequest->meta, 'customer.email', $mediaKitRequest->email_to) }}"
                            >
                                <td>
                                    <code>{{ $mediaKitRequest->uuid ?: $mediaKitRequest->id }}</code>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $mediaKitRequest->source_type }}</div>
                                    @if($mediaKitRequest->source_reference)
                                        <div class="text-muted small">{{ $mediaKitRequest->source_reference }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-secondary" data-mediakit-status>
                                        {{ $mediaKitRequest->status }}
                                    </span>
                                </td>
                                <td style="min-width: 180px;">
                                    <div class="progress" role="progressbar"
                                         aria-valuenow="{{ (int) $mediaKitRequest->progress }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         data-mediakit-progress>
                                        <div class="progress-bar" style="width: {{ (int) $mediaKitRequest->progress }}%" data-mediakit-progress-bar>
                                            {{ (int) $mediaKitRequest->progress }}%
                                        </div>
                                    </div>
                                </td>
                                <td>{{ optional($mediaKitRequest->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-end" data-mediakit-actions>
                                    @if($mediaKitRequest->status === 'completed' && !$mediaKitRequest->deleted_at)
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a href="{{ route('admin.mediakit.download', $mediaKitRequest) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-download me-1"></i>Download
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success" data-mediakit-send-email>
                                                <i class="fa-solid fa-envelope me-1"></i>Invia email
                                            </button>
                                        </div>
                                        <div class="small mt-2 text-muted" data-mediakit-email-status>
                                            @if($mediaKitRequest->email_status === 'sent')
                                                Inviato a {{ $mediaKitRequest->email_to }} il {{ optional($mediaKitRequest->email_sent_at)->format('d/m/Y H:i') }}
                                            @elseif($mediaKitRequest->email_status === 'failed')
                                                <span class="text-danger">Invio fallito: {{ $mediaKitRequest->email_error_message }}</span>
                                            @elseif(in_array($mediaKitRequest->email_status, ['queued', 'sending'], true))
                                                Invio email in corso a {{ $mediaKitRequest->email_to }}
                                            @endif
                                        </div>
                                    @elseif($mediaKitRequest->status === 'failed')
                                        <div class="text-danger small">
                                            <div class="fw-semibold">Generazione fallita</div>
                                            @if($mediaKitRequest->error_message)
                                                <div class="mt-1 text-break">{{ $mediaKitRequest->error_message }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">Non disponibile</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($requests->hasPages())
        <div class="card-footer bg-white">
            {{ $requests->links() }}
        </div>
    @endif
</div>

@if(!$requests->isEmpty())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const terminalStatuses = new Set(['completed', 'failed', 'deleted']);
    const rows = Array.from(document.querySelectorAll('[data-mediakit-row]'));
    let polling = false;
    let timer = null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const badgeClass = (status) => {
        switch (status) {
            case 'completed':
                return 'badge text-bg-success';
            case 'processing':
                return 'badge text-bg-primary';
            case 'failed':
                return 'badge text-bg-danger';
            case 'deleted':
                return 'badge text-bg-dark';
            default:
                return 'badge text-bg-secondary';
        }
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const updateRow = (row, request) => {
        const status = String(request.status || 'queued');
        const progress = Math.max(0, Math.min(100, Number(request.progress || 0)));
        const statusNode = row.querySelector('[data-mediakit-status]');
        const progressNode = row.querySelector('[data-mediakit-progress]');
        const progressBar = row.querySelector('[data-mediakit-progress-bar]');
        const actions = row.querySelector('[data-mediakit-actions]');

        row.dataset.status = status;

        if (statusNode) {
            statusNode.className = badgeClass(status);
            statusNode.textContent = status;
            statusNode.title = request.error_message || '';
        }

        if (progressNode) {
            progressNode.setAttribute('aria-valuenow', String(progress));
        }

        if (progressBar) {
            progressBar.style.width = `${progress}%`;
            progressBar.textContent = `${progress}%`;
        }

        if (!actions) {
            return;
        }

        if (status === 'completed' && !request.deleted_at) {
            let emailStatus = '';

            if (request.email_status === 'sent') {
                emailStatus = `<div class="small mt-2 text-muted">Inviato a ${escapeHtml(request.email_to || '')}</div>`;
            } else if (request.email_status === 'failed') {
                emailStatus = `<div class="small mt-2 text-danger">Invio fallito: ${escapeHtml(request.email_error_message || '')}</div>`;
            } else if (request.email_status === 'queued' || request.email_status === 'sending') {
                emailStatus = `<div class="small mt-2 text-muted">Invio email in corso a ${escapeHtml(request.email_to || '')}</div>`;
            }

            actions.innerHTML =
                '<div class="d-flex flex-wrap justify-content-end gap-2">' +
                `<a href="${escapeHtml(row.dataset.downloadUrl)}" class="btn btn-sm btn-outline-primary">` +
                '<i class="fa-solid fa-download me-1"></i>Download</a>' +
                '<button type="button" class="btn btn-sm btn-outline-success" data-mediakit-send-email>' +
                '<i class="fa-solid fa-envelope me-1"></i>Invia email</button></div>' +
                emailStatus;
        } else if (status === 'failed') {
            actions.innerHTML =
                '<div class="text-danger small">' +
                '<div class="fw-semibold">Generazione fallita</div>' +
                (request.error_message ? `<div class="mt-1 text-break">${escapeHtml(request.error_message)}</div>` : '') +
                '</div>';
        } else if (status === 'deleted') {
            actions.innerHTML = '<span class="text-muted small">Eliminato</span>';
        } else {
            actions.innerHTML = '<span class="text-muted small">Non disponibile</span>';
        }
    };

    const pendingRows = () => rows.filter((row) => !terminalStatuses.has(row.dataset.status));

    const poll = async () => {
        if (polling) {
            return;
        }

        const pending = pendingRows();

        if (pending.length === 0) {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
            return;
        }

        polling = true;

        await Promise.allSettled(
            pending.map(async (row) => {
                const response = await fetch(row.dataset.statusUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    cache: 'no-store',
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                updateRow(row, await response.json());
            })
        );

        polling = false;

        if (pendingRows().length === 0 && timer) {
            clearInterval(timer);
            timer = null;
        }
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mediakit-send-email]');

        if (!button) {
            return;
        }

        const row = button.closest('[data-mediakit-row]');
        const initialEmail = row?.dataset.defaultEmail || '';
        const email = window.prompt('Indirizzo email del cliente:', initialEmail);

        if (email === null || email.trim() === '') {
            return;
        }

        button.disabled = true;

        try {
            const response = await fetch(row.dataset.sendEmailUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({email: email.trim()}),
            });

            const payload = await response.json();

            if (!response.ok) {
                const validation = payload.errors ? Object.values(payload.errors).flat().join('\n') : '';
                throw new Error(validation || payload.message || `HTTP ${response.status}`);
            }

            row.dataset.defaultEmail = email.trim();
            updateRow(row, payload.data);
        } catch (error) {
            window.alert(error.message || 'Invio email non riuscito.');
            button.disabled = false;
        }
    });

    poll();
    timer = window.setInterval(poll, 2000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            poll();
        }
    });
});
</script>
@endif

@endsection
