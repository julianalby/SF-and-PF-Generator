@extends('layouts.base')

@section('body')
    <main class="container">
        <div class="login-card">
            <h1>{{ config('app.name') }}</h1>
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
@endsection
