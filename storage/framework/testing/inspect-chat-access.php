<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$seller = App\Models\User::where('email', 'seller@ezicart.com')->firstOrFail();
$messaging = app(App\Services\DirectMessagingService::class);
$result = [];
foreach (App\Models\DirectConversation::forUser($seller)->get() as $conversation) {
    $partner = $conversation->otherUser($seller);
    if ($partner->isCommerceAdmin()) {
        $result[] = ['conversation_id' => $conversation->id, 'seller_id' => $seller->id, 'seller_status' => $seller->status, 'partner_id' => $partner->id, 'partner_role' => $partner->role, 'partner_status' => $partner->status, 'seller_can_contact' => $messaging->canContact($seller, $partner), 'admin_can_contact' => $messaging->canContact($partner, $seller)];
    }
}
echo json_encode($result, JSON_PRETTY_PRINT);
