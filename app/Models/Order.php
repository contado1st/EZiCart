<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

class Order extends Model
{
    use HasFactory;

    private bool $parcelCodeRotationAllowed = false;

    protected $fillable = [
        'recipient_name',
        'recipient_contact',
        'province',
        'region',
        'municipality',
        'barangay',
        'street_address',
        'house_number',
        'postal_code',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->parcel_code)) {
                $order->parcel_code = Str::upper(Str::random(24));
            }
        });

        static::updating(function (Order $order): void {
            if ($order->isDirty('parcel_code') && filled($order->getOriginal('parcel_code')) && ! $order->parcelCodeRotationAllowed) {
                throw new LogicException('Parcel identifiers are immutable after assignment.');
            }
        });
    }

    public function rotateParcelCodeForReprint(string $parcelCode, int $version, User $seller): void
    {
        abort_unless($seller->role === 'seller' && $this->seller_id === $seller->id, 403);
        abort_unless(
            $this->exists
                && in_array($this->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true)
                && $this->pickup_claimed_at === null
                && $this->seller_handover_at === null
                && $version === (int) $this->parcel_code_version + 1,
            422,
            'A parcel label can only be replaced before physical pickup begins.',
        );

        $this->parcelCodeRotationAllowed = true;

        try {
            $this->forceFill([
                'parcel_code' => $parcelCode,
                'parcel_code_version' => $version,
            ])->save();
        } finally {
            $this->parcelCodeRotationAllowed = false;
        }
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function finance(): HasOne
    {
        return $this->hasOne(OrderFinance::class);
    }

    public function refundExecutions(): HasMany
    {
        return $this->hasMany(RefundExecution::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(SellerPayout::class);
    }

    public function activeTransfer(): HasOne
    {
        return $this->hasOne(ParcelTransfer::class, 'active_order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function pickupCourier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pickup_courier_id');
    }

    public function deliveryCourier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_courier_id');
    }

    public function sortingCenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    public function destinationArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'destination_area_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_code', 'code');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class)->orderBy('attempt_no');
    }

    public function deliveryAssignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class)->orderBy('assigned_at')->orderBy('id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(OrderConversation::class);
    }

    public function pendingCancellation(): HasOne
    {
        return $this->hasOne(OrderCancellationRequest::class, 'active_order_id');
    }

    /** @return Collection<int, User> */
    public function messageParticipants(): Collection
    {
        $participantIds = collect([$this->buyer_id, $this->seller_id, $this->pickup_courier_id, $this->delivery_courier_id, $this->sorting_center_id])
            ->merge($this->trackingEvents()->whereHas('actor', fn ($query) => $query->where('role', 'sorting_center'))->pluck('actor_id'))
            ->filter()
            ->unique()
            ->values();

        return User::query()->whereIn('id', $participantIds)->get();
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ParcelTrackingEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function scopeActiveCourierWorkload(Builder $query, bool $includeFailed = true): Builder
    {
        $statuses = ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'];
        if ($includeFailed) {
            $statuses[] = 'DELIVERY_FAILED';
        }

        return $query->where(function (Builder $query) use ($statuses): void {
            $query->whereIn('status', $statuses)
                ->orWhere(fn (Builder $query): Builder => $query->where('status', 'RETURN_IN_TRANSIT')->whereNull('return_handed_to_seller_at'));
        });
    }

    protected function casts(): array
    {
        return [
            'seller_response_due_at' => 'datetime',
            'preparation_due_at' => 'datetime',
            'seller_reminded_at' => 'datetime',
            'restock_inspected_at' => 'datetime',
            'restock_approved' => 'boolean',
            'picked_up_at' => 'datetime',
            'pickup_claimed_at' => 'datetime',
            'pickup_requested_at' => 'datetime',
            'pickup_scheduled_for' => 'datetime',
            'pickup_arrived_at' => 'datetime',
            'seller_handover_at' => 'datetime',
            'received_at' => 'datetime',
            'sorted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'hub_released_at' => 'datetime',
            'delivery_recovered_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'return_handed_to_seller_at' => 'datetime',
            'inventory_restored_at' => 'datetime',
            'delivery_code_expires_at' => 'datetime',
            'delivery_code_used_at' => 'datetime',
        ];
    }
}
