@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>
    <p class="muted">
        Welcome, {{ auth()->user()->username }}.
        <span class="badge @if (auth()->user()->isAdmin()) badge-admin @endif">{{ auth()->user()->role }}</span>
    </p>

    <div class="grid-cards">
        <a class="tile" href="{{ route('sf.create') }}">
            <h3>Generate SF</h3>
            <p class="muted">Create a Service Form and get its SF number.</p>
        </a>
        <a class="tile" href="{{ route('pf.create') }}">
            <h3>Generate PF</h3>
            <p class="muted">Create a Project Form and get its PF number.</p>
        </a>
    </div>

    @if (auth()->user()->isAdmin())
        <h2>Administration</h2>
        <div class="grid-cards">
            <a class="tile" href="{{ route('admin.sf.index') }}">
                <h3>SF Database</h3>
                <p class="muted">All Service Forms.</p>
            </a>
            <a class="tile" href="{{ route('admin.pf.index') }}">
                <h3>PF Database</h3>
                <p class="muted">All Project Forms.</p>
            </a>
            <a class="tile" href="{{ route('admin.users.index') }}">
                <h3>User Management</h3>
                <p class="muted">View and create users.</p>
            </a>
        </div>
    @endif
@endsection
