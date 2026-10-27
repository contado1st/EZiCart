@extends('layouts.app')

@section('title', 'Privacy Policy | EZiCart')

@section('content')
    <main class="container legal-page">
        <h1>Privacy Policy</h1>
        <p>Version {{ config('registration.policy_version') }}. This notice describes the personal information EZiCart uses to operate accounts and marketplace services.</p>

        <h2>Information collected</h2>
        <p>Depending on your role, EZiCart may store your name, email address, mobile number, date of birth, address, business identifiers, uploaded application documents, and account activity needed to provide and secure the service.</p>

        <h2>How information is used</h2>
        <p>Information is used to create and protect accounts, verify email addresses, review seller and logistics applications, coordinate orders and deliveries, prevent fraud, and meet operational or legal obligations. Email is verified during registration. Mobile number format and uniqueness are checked, but mobile ownership is not verified by SMS.</p>

        <h2>Access, retention, and security</h2>
        <p>For signed-in buyers, viewed products from the last 90 days and delivered or completed purchase categories help personalize the For you page. You can clear your browsing history from that page; eligible purchases can still inform recommendations. Product stock alerts are optional and can be removed from Stock alerts in your account.</p>
        <p>Private application documents are available only through authorized application routes. Information is retained while needed for the account, application, transactions, and applicable obligations. EZiCart uses access controls and other safeguards, but no online service can promise absolute security.</p>

        <h2>Your choices and contact</h2>
        <p>You may request access, correction, or other assistance through EZiCart support using the contact details published in the application. This policy should be reviewed by qualified privacy counsel before production use.</p>
    </main>
@endsection
