<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_name',
        'recipient_contact',
        'province',
        'municipality',
        'barangay',
        'street_address',
        'notes',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
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
            'picked_up_at' => 'datetime',
            'pickup_claimed_at' => 'datetime',
            'pickup_requested_at' => 'datetime',
            'pickup_scheduled_for' => 'datetime',
            'pickup_arrived_at' => 'datetime',
            'seller_handover_at' => 'datetime',
            'received_at' => 'datetime',
            'sorted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'return_handed_to_seller_at' => 'datetime',
            'inventory_restored_at' => 'datetime',
        ];
    }
}
