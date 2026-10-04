<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'buyer_id',
        'seller_id',
        'courier_id',
        'pickup_courier_id',
        'delivery_courier_id',
        'sorting_center_id',
        'recipient_name',
        'recipient_contact',
        'province',
        'municipality',
        'barangay',
        'street_address',
        'delivery_area',
        'destination_area_id',
        'subtotal',
        'shipping_fee',
        'commission_fee',
        'total_amount',
        'payment_method',
        'status',
        'notes',
        'voucher_code',
        'discount_amount',
        'picked_up_at',
        'pickup_claimed_at',
        'pickup_requested_at',
        'pickup_scheduled_for',
        'pickup_window',
        'pickup_notes',
        'pickup_arrived_at',
        'seller_handover_at',
        'received_at',
        'sorted_at',
        'assigned_at',
        'out_for_delivery_at',
        'delivered_at',
        'failed_at',
        'delivery_failure_reason',
        'delivery_notes',
        'cod_collected_amount',
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

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ParcelTrackingEvent::class)->orderByDesc('created_at')->orderByDesc('id');
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
            'inventory_restored_at' => 'datetime',
        ];
    }
}
