@extends('layouts.frontend')

@section('title', 'Home')

@section('content')
  <div class="p-4 bg-body-tertiary rounded">
    <h1 class="h3 mb-2">Home</h1>
    <p class="mb-0">
      Locale corrente: <strong>{{ app()->getLocale() }}</strong>
    </p>
  </div>
@endsection