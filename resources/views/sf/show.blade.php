@extends('layouts.app')

@section('title', 'SF No. '.$record->sf_number)

@section('content')
    <div class="narrow">
        @if (session('created'))
            <div class="alert alert-success" role="status"><strong>Service Form created successfully.</strong></div>
        @else
            <h1>Service Form</h1>
        @endif

        <div class="card result">
            <div class="result-line">SF No.: <strong id="generated-number">{{ $record->sf_number }}</strong></div>
            <button type="button" class="btn" data-copy="#generated-number">Copy number</button>
        </div>

        <h2>Details</h2>
        <div class="card">
            <dl class="details">
                <dt>Create Date</dt>
                <dd>{{ $record->created_at->format(config('sfpf.date_format')) }}</dd>
                <dt>User</dt>
                <dd>{{ $record->user }}</dd>
                <dt>VNID</dt>
                <dd>{{ $record->vnid }}</dd>
                <dt>Customer Name</dt>
                <dd>{{ $record->customer_name }}</dd>
                <dt>Service</dt>
                <dd>{{ $record->service }}</dd>
            </dl>
        </div>

        <div class="actions">
            <a class="btn btn-primary" href="{{ route('sf.create') }}">Create another SF</a>
            <a class="btn" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div>
@endsection
