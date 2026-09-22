@extends('layouts.app')

@section('title', 'PF No. '.$record->pf_number)

@section('content')
    <div class="narrow">
        @if (session('created'))
            <div class="alert alert-success" role="status"><strong>Project Form created successfully.</strong></div>
        @else
            <h1>Project Form</h1>
        @endif

        <div class="card result">
            <div class="result-line">PF No.: <strong id="generated-number">{{ $record->pf_number }}</strong></div>
            <button type="button" class="btn" data-copy="#generated-number">Copy number</button>
        </div>

        <h2>Details</h2>
        <div class="card">
            <dl class="details">
                <dt>Create Date</dt>
                <dd>{{ $record->created_at->format(config('sfpf.date_format')) }}</dd>
                <dt>User</dt>
                <dd>{{ $record->user }}</dd>
                <dt>Project Name</dt>
                <dd>{{ $record->project_name }}</dd>
                <dt>SF Number</dt>
                <dd>{{ filled($record->sf_number) ? $record->sf_number : '—' }}</dd>
                <dt>VNID</dt>
                <dd>{{ filled($record->vnid) ? $record->vnid : '—' }}</dd>
                <dt>Customer Name</dt>
                <dd>{{ filled($record->customer_name) ? $record->customer_name : '—' }}</dd>
            </dl>
        </div>

        <div class="actions">
            <a class="btn btn-primary" href="{{ route('pf.create') }}">Create another PF</a>
            <a class="btn" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div>
@endsection
