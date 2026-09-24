<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $created_by
 * @property string $title
 * @property string $content
 * @property string $type
 * @property string $target_role
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $author
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement activeForUser(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereTargetRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereUpdatedAt($value)
 */
	class Announcement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property int $buyer_id
 * @property int $seller_id
 * @property string $reason
 * @property string $description
 * @property string|null $evidence_path
 * @property string $status
 * @property string|null $admin_notes
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $buyer
 * @property-read \App\Models\Order $order
 * @property-read \App\Models\User $seller
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereAdminNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereEvidencePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereResolvedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereSellerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dispute whereUpdatedAt($value)
 */
	class Dispute extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $order_number
 * @property int $buyer_id
 * @property int $seller_id
 * @property int|null $courier_id
 * @property int|null $pickup_courier_id
 * @property int|null $delivery_courier_id
 * @property int|null $sorting_center_id
 * @property string $recipient_name
 * @property string $recipient_contact
 * @property string $province
 * @property string $municipality
 * @property string $barangay
 * @property string $street_address
 * @property string|null $delivery_area
 * @property numeric $subtotal
 * @property string|null $voucher_code
 * @property numeric $discount_amount
 * @property numeric $shipping_fee
 * @property numeric $commission_fee
 * @property numeric $total_amount
 * @property string $payment_method
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $buyer
 * @property-read \App\Models\User|null $deliveryCourier
 * @property-read \App\Models\Dispute|null $dispute
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\User|null $pickupCourier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \App\Models\User $seller
 * @property-read \App\Models\User|null $sortingCenter
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Voucher> $vouchers
 * @property-read int|null $vouchers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBarangay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCommissionFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCourierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDeliveryArea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDeliveryCourierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereMunicipality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePickupCourierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereRecipientContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereRecipientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSellerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSortingCenterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStreetAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereVoucherCode($value)
 */
	class Order extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string|null $variation_info
 * @property string $product_name
 * @property numeric $unit_price
 * @property int $quantity
 * @property numeric $item_total
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order $order
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereItemTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereVariationInfo($value)
 */
	class OrderItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string $category
 * @property numeric $price
 * @property int $stock
 * @property string|null $image_path
 * @property int $is_archived
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read float $average_rating
 * @property-read int $review_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \App\Models\User $seller
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductVariation> $variations
 * @property-read int|null $variations_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsArchived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUserId($value)
 */
	class Product extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property string $type
 * @property string $value
 * @property numeric $price_adjustment
 * @property int $stock
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation wherePriceAdjustment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation whereStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariation whereValue($value)
 */
	class ProductVariation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $buyer_id
 * @property int $rating
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $buyer
 * @property-read \App\Models\Order $order
 * @property-read \App\Models\Product $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereUpdatedAt($value)
 */
	class Review extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string $status
 * @property string|null $suspension_reason
 * @property string|null $suspended_at
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_initial
 * @property string $sex
 * @property string $contact_no
 * @property \Illuminate\Support\Carbon $birthday
 * @property int $age
 * @property string $province
 * @property string $municipality
 * @property string $barangay
 * @property string $street_address
 * @property string|null $id_upload_path
 * @property string|null $business_name
 * @property string|null $line_of_business
 * @property string|null $assigned_area
 * @property string|null $business_permit_path
 * @property string|null $vehicle_type
 * @property string|null $plate_number
 * @property string|null $id_path
 * @property string|null $permit_path
 * @property string|null $license_path
 * @property string|null $or_cr_path
 * @property string|null $or_cr_upload_path
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Announcement> $announcements
 * @property-read int|null $announcements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $buyerOrders
 * @property-read int|null $buyer_orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $courierDeliveries
 * @property-read int|null $courier_deliveries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $finalDeliveries
 * @property-read int|null $final_deliveries_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $pickupDeliveries
 * @property-read int|null $pickup_deliveries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $sellerOrders
 * @property-read int|null $seller_orders_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAssignedArea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBarangay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBirthday($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBusinessName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBusinessPermitPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereContactNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIdPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIdUploadPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLicensePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLineOfBusiness($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMiddleInitial($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMunicipality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOrCrPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOrCrUploadPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePermitPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePlateNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStreetAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSuspendedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSuspensionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereVehicleType($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $seller_id
 * @property string $code
 * @property string $type
 * @property numeric $value
 * @property numeric $min_spend
 * @property int|null $usage_limit
 * @property int $used_count
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $seller
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereMinSpend($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereSellerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereUsageLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereUsedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Voucher whereValue($value)
 */
	class Voucher extends \Eloquent {}
}

