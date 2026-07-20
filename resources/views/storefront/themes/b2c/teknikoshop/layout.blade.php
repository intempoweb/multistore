@extends('storefront.themes.b2c.ciak.layout')

@push('styles')
    <link href="{{ asset('css/themes/b2c/teknikoshop.css') }}?v={{ @filemtime(public_path('css/themes/b2c/teknikoshop.css')) }}" rel="stylesheet">
@endpush
