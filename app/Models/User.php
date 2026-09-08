<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'role',
        'status',
        'first_name',
        'last_name',
        'middle_initial',
        'sex',
        'contact_no',
        'birthday',
        'age',
        'province',
        'municipality',
        'barangay',
        'street_address',
        'id_upload_path',
        'business_name',
        'line_of_business',
        'business_permit_path',
        'vehicle_type',
        'plate_number',
        'or_cr_upload_path',
        'id_path',
        'permit_path',
        'license_path',
        'or_cr_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
        ];
    }

    /**
     * Products listed by the seller.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Add inside app/Models/User.php

    /**
     * Orders placed by this user as a buyer.
     */
    public function buyerOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order::class, 'buyer_id');
    }

    /**
     * Orders received by this user as a seller.
     */
    public function sellerOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order::class, 'seller_id');
    }

    /**
     * Orders dispatched or delivered by this user as a courier.
     */
    public function courierDeliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order::class, 'courier_id');
    }

    /**
     * Parcels claimed by this courier for seller pickup.
     */
    public function pickupDeliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order::class, 'pickup_courier_id');
    }

    /**
     * Parcels assigned to this courier for doorstep delivery.
     */
    public function finalDeliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order::class, 'delivery_courier_id');
    }
    
}