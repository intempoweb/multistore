@extends($storefrontLayout)

@section('title', $store->name)

@section('content')

<div class="text-center py-5">

<h1 class="display-6 fw-bold">
{{ $store->name }}
</h1>

<p class="text-muted">
Homepage B2C default
</p>

</div>

@endsection