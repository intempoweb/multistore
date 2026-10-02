@extends('storefront.themes.b2b.default.layout')

@php
    $themeCssPath = 'css/themes/b2b/intempodistribution.css';
    $themeJsPath = 'js/themes/b2b/intempodistribution.js';
    $themeCssVersion = file_exists(public_path($themeCssPath)) ? filemtime(public_path($themeCssPath)) : null;
    $themeJsVersion = file_exists(public_path($themeJsPath)) ? filemtime(public_path($themeJsPath)) : null;
@endphp

@push('styles')
    <link href="{{ asset($themeCssPath) }}@if($themeCssVersion)?v={{ $themeCssVersion }}@endif" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset($themeJsPath) }}@if($themeJsVersion)?v={{ $themeJsVersion }}@endif" defer></script>
@endpush
