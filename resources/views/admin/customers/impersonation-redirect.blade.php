<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0;url={{ $targetUrl }}">
    <title>Accesso cliente</title>
    <script>
        window.location.replace(@json($targetUrl));
    </script>
</head>
<body>
    <main style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 2rem;">
        <p>Accesso a {{ $store->name }} in corso...</p>
        <p>
            <a href="{{ $targetUrl }}">Continua come {{ $customer->ragsoanag_cg16 }}</a>
        </p>
    </main>
</body>
</html>
