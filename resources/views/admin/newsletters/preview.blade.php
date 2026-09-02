<!doctype html>
<html lang="{{ str_replace('_', '-', $newsletter->locale) }}">
<head>
    <meta charset="utf-8">
    <title>Preview - {{ $newsletter->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f3f4f6;">
    {!! $html !!}
</body>
</html>
