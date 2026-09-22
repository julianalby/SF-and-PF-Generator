<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') @yield('heading') &middot; {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main class="container">
    <div class="card error-card">
        <p class="error-code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p class="muted">@yield('message')</p>
        <p><a class="btn btn-primary" href="{{ url('/') }}">Back to the application</a></p>
    </div>
</main>
</body>
</html>
