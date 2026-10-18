@extends('layouts.app')

@section('title', 'Terms of Service | EZiCart')

@section('content')
    <main class="container legal-page">
        <h1>Terms of Service</h1>
        <p>Version {{ config('registration.policy_version') }}. These terms explain the basic rules for using EZiCart while the marketplace is being operated and reviewed.</p>

        <h2>Accounts</h2>
        <p>Give accurate account and contact details, keep your password private, and notify EZiCart if you believe someone else has accessed your account. Email must be verified before a new account can be used. Seller and logistics applications remain under review until approved.</p>

        <h2>Marketplace activity</h2>
        <p>Use EZiCart to list, buy, fulfill, and manage goods and services lawfully. Do not submit false business or identity records, interfere with the marketplace, or misuse another person’s account or information.</p>

        <h2>Account review and access</h2>
        <p>EZiCart may review seller and logistics applications and may restrict or suspend an account when required to protect users or the service. Applicants may correct and resubmit a rejected application through the provided flow.</p>

        <h2>Changes and contact</h2>
        <p>These terms may be revised as the service changes. The version shown above is recorded when you consent during registration. Contact EZiCart support through the contact details published in the application for questions.</p>
    </main>
@endsection
