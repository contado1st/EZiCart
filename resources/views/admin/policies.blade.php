@extends('layouts.admin')

@section('title', 'Platform Policies & Governance - Admin Portal')

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Platform Policies & Governance Rules</h1>
        <p class="dash-subtitle">Establish operational regulations, manage merchant commission rates, and broadcast binding platform policies.</p>
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success" style="margin-bottom: 1.5rem;">✅ {{ session('success') }}</div>
@endif

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Left Column: Core Platform Rules & Operational Policies -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- 1. Commission & Transaction Policy -->
        <div class="dash-panel" style="padding: 1.75rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        💰
                    </div>
                    <div>
                        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--slate-900); margin: 0;">1. Commission & Settlement Policy</h2>
                        <span style="font-size: 0.75rem; color: var(--slate-500);">Financial governance for marketplace transactions</span>
                    </div>
                </div>
                <span class="status-pill status-completed" style="font-weight: 700;">Active 10% Rate</span>
            </div>
            <div style="font-size: 0.875rem; color: var(--slate-700); line-height: 1.6;">
                <p style="margin-bottom: 0.75rem;">
                    EZiCart assesses a standard <strong>10% platform commission fee</strong> on all fulfilled and settled customer orders (`DELIVERED` or `COMPLETED` milestones).
                </p>
                <ul style="padding-left: 1.25rem; margin-bottom: 0.75rem; display: flex; flex-direction: column; gap: 0.4rem;">
                    <li><strong>Settlement Basis:</strong> The 10% commission fee is calculated against the net product subtotal following seller-funded promotional discounts.</li>
                    <li><strong>Buyer Vouchers:</strong> Platform-wide vouchers and delivery fees are accounted for in the consolidated platform ledger.</li>
                    <li><strong>Failed / Returned Orders:</strong> Orders marked as `CANCELLED` or returned prior to completion incur 0% commission.</li>
                </ul>
            </div>
        </div>

        <!-- 2. Product Listing & Seller Verification Policy -->
        <div class="dash-panel" style="padding: 1.75rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: #fdf2f8; color: var(--dash-primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        📦
                    </div>
                    <div>
                        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--slate-900); margin: 0;">2. Merchant Onboarding & Product Standards</h2>
                        <span style="font-size: 0.75rem; color: var(--slate-500);">Catalog integrity, verification credentials & content safety</span>
                    </div>
                </div>
                <span class="status-pill status-completed" style="font-weight: 700;">Mandatory</span>
            </div>
            <div style="font-size: 0.875rem; color: var(--slate-700); line-height: 1.6;">
                <ul style="padding-left: 1.25rem; margin-bottom: 0.75rem; display: flex; flex-direction: column; gap: 0.4rem;">
                    <li><strong>KYC / Business Credentials:</strong> All prospective sellers must furnish a valid government ID and municipal business permit before catalog publishing rights are granted.</li>
                    <li><strong>Image Requirements:</strong> Listings must feature authentic, clear high-resolution product photography. Products with variations (color, size, storage) must illustrate options accurately.</li>
                    <li><strong>Prohibited Items:</strong> Hazardous materials, prescription narcotics, illegal narcotics, counterfeit merchandise, and unlicensed goods are strictly forbidden. Infractions trigger immediate account termination.</li>
                </ul>
            </div>
        </div>

        <!-- 3. Buyer Protection & Dispute Arbitration -->
        <div class="dash-panel" style="padding: 1.75rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: #fef3c7; color: #b45309; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        ⚖️
                    </div>
                    <div>
                        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--slate-900); margin: 0;">3. Buyer Protection & Dispute Arbitration</h2>
                        <span style="font-size: 0.75rem; color: var(--slate-500);">Fair arbitration protocols for damaged, incorrect or non-received items</span>
                    </div>
                </div>
                <span class="status-pill status-completed" style="font-weight: 700;">7-Day Window</span>
            </div>
            <div style="font-size: 0.875rem; color: var(--slate-700); line-height: 1.6;">
                <ul style="padding-left: 1.25rem; margin-bottom: 0.75rem; display: flex; flex-direction: column; gap: 0.4rem;">
                    <li><strong>Filing Grace Period:</strong> Buyers are afforded a 7-day window following delivery confirmation to submit a formal dispute with photographic or unboxing evidence.</li>
                    <li><strong>Arbitration Process:</strong> Platform administrators review claims objectively. Available remedies include full refund (`REFUND_APPROVED`), merchant replacement (`REPLACEMENT_APPROVED`), or claim dismissal (`REJECTED`) when unfounded.</li>
                    <li><strong>Dispute Abuse:</strong> Buyers exhibiting fraudulent or repeatedly dishonest return patterns will face profile restrictions.</li>
                </ul>
            </div>
        </div>

        <!-- 4. Account Sanctions & Enforcement -->
        <div class="dash-panel" style="padding: 1.75rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: #fee2e2; color: #b91c1c; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        🛡️
                    </div>
                    <div>
                        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--slate-900); margin: 0;">4. Platform Sanctions & Account Suspension</h2>
                        <span style="font-size: 0.75rem; color: var(--slate-500);">Disciplinary tiers for policy violations</span>
                    </div>
                </div>
                <span class="status-pill status-badge-suspended" style="font-weight: 700;">Zero Tolerance</span>
            </div>
            <div style="font-size: 0.875rem; color: var(--slate-700); line-height: 1.6;">
                <ul style="padding-left: 1.25rem; margin-bottom: 0.75rem; display: flex; flex-direction: column; gap: 0.4rem;">
                    <li><strong>Seller Fulfillment Strike:</strong> Unreasonable order cancellation rates (>15%) or refusal to dispatch parcels will result in temporary store suspension.</li>
                    <li><strong>Immediate Suspension:</strong> Confirmed fraudulent activities, deceptive pricing bait-and-switch, or abusive communication results in immediate platform lockout.</li>
                    <li><strong>Reactivation Review:</strong> Suspended users must submit proof of remediation to the Admin Moderation Board before account restoration can be approved.</li>
                </ul>
            </div>
        </div>

    </div>

    <!-- Right Column: Broadcast Policy Update & Historical Advisories -->
    <div>
        <!-- Form Card -->
        <div class="controls-card" style="margin-bottom: 1.5rem;">
            <h2 class="controls-title" style="display: flex; align-items: center; gap: 0.5rem;">
                📢 Broadcast Policy Advisory
            </h2>
            <p style="font-size: 0.8rem; color: var(--slate-500); margin-bottom: 1rem;">
                Publish a platform-wide operational or legal policy update visible to all buyers, sellers, and administrators.
            </p>

            <form action="{{ route('admin.policies.update') }}" method="POST">
                @csrf

                <div class="controls-field">
                    <label class="controls-label">Advisory Headline *</label>
                    <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Updated Product Listing Guidelines (Q4 2026)" class="controls-input" required>
                </div>

                <div class="controls-field">
                    <label class="controls-label">Policy Content & Legal Terms *</label>
                    <textarea name="content" rows="6" class="controls-textarea" placeholder="Detail the binding policy update, effective dates, and compliance mandates..." required>{{ old('content') }}</textarea>
                </div>

                <button type="submit" class="dash-btn-primary" style="width: 100%; justify-content: center; margin-top: 0.5rem;">
                    Publish Platform Policy Advisory
                </button>
            </form>
        </div>

        <!-- Recent Policy Bulletins -->
        <div class="dash-panel" style="padding: 1.25rem;">
            <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--slate-900); margin-bottom: 0.75rem;">
                📜 Recent Platform Advisories
            </h3>

            @forelse($policyAnnouncements as $announcement)
                <div style="border-bottom: 1px solid var(--slate-100); padding-bottom: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="font-weight: 700; font-size: 0.85rem; color: var(--slate-800);">{{ $announcement->title }}</div>
                    <div style="font-size: 0.75rem; color: var(--slate-600); margin-top: 0.2rem; line-height: 1.4;">
                        {{ \Illuminate\Support\Str::limit($announcement->content, 85) }}
                    </div>
                    <div style="font-size: 0.7rem; color: var(--slate-400); margin-top: 0.35rem;">
                        Published {{ $announcement->created_at->format('M d, Y') }}
                    </div>
                </div>
            @empty
                <div style="font-size: 0.8rem; color: var(--slate-500); font-style: italic;">
                    No public policy announcements posted yet.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

