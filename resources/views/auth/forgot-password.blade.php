@extends('layouts.app')

@section('content')
    <div class="auth-wrapper">
        <div class="auth-card u-extracted-eb1967695a">
            <section class="auth-form-container">
                <h1 class="auth-title">Reset your password</h1>
                <p>Enter the email address associated with your EZiCart account. If it matches an account, we’ll email a
                    reset link.</p>

                @if (session('status'))
                    <div class="form-notice">{{ session('status') }}</div>
                @endif

                <form action="{{ route('password.email') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="email">Email address</label>
                        <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}"
                            required autocomplete="email">
                        @error('email')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn-submit">Send reset link</button>
                </form>

                <p class="auth-footer-text"><a href="{{ route('login') }}">Back to login</a></p>
            </section>
        </div>
    </div>
@endsection
