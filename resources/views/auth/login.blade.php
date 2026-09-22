@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <div class="card">
        <h2>Sign in</h2>

        <form method="POST" action="{{ route('login.store') }}" data-submit-once>
            @csrf

            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" maxlength="255"
                       required autofocus autocomplete="username" @error('username') class="is-invalid" @enderror>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" maxlength="255"
                       required autocomplete="current-password" @error('password') class="is-invalid" @enderror>
            </div>

            <button type="submit" class="btn btn-primary">Sign in</button>
        </form>
    </div>
@endsection
