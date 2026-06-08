<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Admin')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- FontAwesome --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 250px;
            background: #212529;
            color: #fff;
        }

        .admin-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            font-size: 1.1rem;
        }

        .nav-link.active {
            background-color: #495057 !important;
        }
    </style>
</head>
<body>

<div class="admin-wrapper">

    {{-- Sidebar --}}
    <div class="admin-sidebar">
        @include('admin.partials.sidebar')
    </div>

    {{-- Content --}}
    <div class="admin-content">

        {{-- Topbar --}}
        @include('admin.partials.topbar')

        {{-- Page content --}}
        <main class="p-4">
            @yield('content')
        </main>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>