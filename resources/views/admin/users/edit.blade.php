@extends('layouts.app')

@section('title', 'Reset password')

@section('content')
    <div class="narrow">
        <h1>Reset password</h1>
        <p class="muted">User: <strong>{{ $user->username }}</strong> ({{ $user->role }})</p>

        <form method="POST" action="{{ route('admin.users.password', $user) }}" class="card" data-submit-once>
            @csrf
            @method('PUT')

            <div class="field">
                <label for="password">New password <span class="req">*</span></label>
                <input id="password" name="password" type="password" maxlength="255"
                       required autofocus autocomplete="new-password" @error('password') class="is-invalid" @enderror>
                <div class="hint">At least 8 characters. Stored only as an Argon2id hash.</div>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm new password <span class="req">*</span></label>
                <input id="password_confirmation" name="password_confirmation" type="password" maxlength="255"
                       required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">Save new password</button>
            <a class="btn" href="{{ route('admin.users.index') }}">Cancel</a>
        </form>
    </div>
@endsection
