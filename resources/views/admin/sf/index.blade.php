@extends('layouts.app')

@section('title', 'SF Database')

@section('content')
    <h1>SF Database</h1>

    <div class="toolbar">
        <span class="muted">Total: {{ $records->total() }} record(s)</span>
        <span>
            Order:
            @if ($order === 'asc')
                <strong>Oldest first</strong> &middot; <a href="{{ request()->fullUrlWithQuery(['order' => 'desc', 'page' => null]) }}">Newest first</a>
            @else
                <a href="{{ request()->fullUrlWithQuery(['order' => 'asc', 'page' => null]) }}">Oldest first</a> &middot; <strong>Newest first</strong>
            @endif
        </span>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th class="num">No</th>
                    <th>Create Date</th>
                    <th>User</th>
                    <th class="num">SF Number</th>
                    <th>VNID</th>
                    <th>Customer Name</th>
                    <th>Service</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="num">{{ $record->id }}</td>
                        <td>{{ $record->created_at->format(config('sfpf.date_format')) }}</td>
                        <td>{{ $record->user }}</td>
                        <td class="num">{{ $record->sf_number }}</td>
                        <td>{{ $record->vnid }}</td>
                        <td>{{ $record->customer_name }}</td>
                        <td>{{ $record->service }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No SF records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $records->links() }}
@endsection
