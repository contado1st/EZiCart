@extends('layouts.app')

@section('content')
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 560px;">
            <section class="auth-form-container">
                <h1 class="auth-title">Choose a new password</h1>

                <form action="{{ route('password.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="form-group">
                        <label class="form-label" for="email">Email address</label>
                        <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required autocomplete="email">
                        @error('email')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">New password</label>
                        <input id="password" type="password" name="password" class="form-control" required autocomplete="new-password">
                        @error('password')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password-confirmation">Confirm new password</label>
                        <input id="password-confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn-submit">Reset password</button>
                </form>
            </section>
        </div>
    </div>
@endsection
