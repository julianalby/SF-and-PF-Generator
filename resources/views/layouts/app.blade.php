@extends('layouts.base')

@section('body')
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>

            <nav class="nav" aria-label="Main navigation">
                <a href="{{ route('sf.create') }}" @class(['active' => request()->routeIs('sf.*')])>Generate SF</a>
                <a href="{{ route('pf.create') }}" @class(['active' => request()->routeIs('pf.*')])>Generate PF</a>

                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.sf.index') }}" @class(['active' => request()->routeIs('admin.sf.*')])>SF Database</a>
                    <a href="{{ route('admin.pf.index') }}" @class(['active' => request()->routeIs('admin.pf.*')])>PF Database</a>
                    <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')])>User Management</a>
                @endif
            </nav>

            <div class="userbox">
                <span>Logged in as: <strong>{{ auth()->user()->username }}</strong></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-link">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <main class="container">
        @include('partials.flash')
        @yield('content')
    </main>
@endsection
