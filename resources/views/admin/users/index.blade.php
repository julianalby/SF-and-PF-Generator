@extends('layouts.app')

@section('title', 'User Management')

@section('content')
    <h1>User Management</h1>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th class="num">No</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td class="num">{{ $user->id }}</td>
                        <td>{{ $user->username }}</td>
                        <td><span class="badge @if ($user->isAdmin()) badge-admin @endif">{{ $user->role }}</span></td>
                        <td><a href="{{ route('admin.users.edit', $user) }}">Reset password</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>Create user</h2>
    <form method="POST" action="{{ route('admin.users.store') }}" class="card narrow" data-submit-once>
        @csrf

        <div class="field">
            <label for="username">Username <span class="req">*</span></label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" maxlength="50"
                   required autocomplete="off" @error('username') class="is-invalid" @enderror>
            <div class="hint">Letters, numbers, dots, underscores and hyphens. Not case-sensitive.</div>
        </div>

        <div class="field">
            <label for="role">Role <span class="req">*</span></label>
            <select id="role" name="role" required @error('role') class="is-invalid" @enderror>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role', 'User') === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="password">Password <span class="req">*</span></label>
            <input id="password" name="password" type="password" maxlength="255"
                   required autocomplete="new-password" @error('password') class="is-invalid" @enderror>
            <div class="hint">At least 8 characters. Stored only as an Argon2id hash.</div>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password <span class="req">*</span></label>
            <input id="password_confirmation" name="password_confirmation" type="password" maxlength="255"
                   required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Create user</button>
    </form>
@endsection
