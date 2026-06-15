@extends($storefrontLayout)

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-3">Clienti agente</h1>

    <div class="row g-3">
        @foreach($customers as $customer)
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6">{{ $customer->ragsoanag_cg16 ?: 'Cliente '.$customer->clifor_cg44 }}</h2>
                        <div class="small text-muted mb-3">
                            Codice: {{ $customer->clifor_cg44 }}<br>
                            Email: {{ $customer->indemail_cg16 ?: '—' }}
                        </div>

                        <form method="POST" action="{{ route('storefront.agent.customers.impersonate', $customer) }}">
                            @csrf
                            <button class="btn btn-dark btn-sm w-100">
                                Entra come cliente
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</div>
@endsection