@extends($storefrontLayout)

@section('title', $store->name)

@section('content')

<div class="bg-white p-4 rounded shadow-sm">

<h1 class="h3 fw-bold">
{{ $store->name }}
</h1>

<p class="text-muted">
Homepage B2B default.
</p>

</div>

@endsection