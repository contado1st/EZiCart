<?php

return [
    // Ordered by specificity; GET route names describe the destination rather than the outgoing action.
    'profiles' => [
        'shop' => ['shops.show'],
        'profile' => ['account.profile.edit'],
        'product' => ['product.show'],
        'checkout' => ['checkout.index'],
        'cart' => ['cart.index'],
        'analytics' => ['analytics.index'],
        'announcements' => ['admin.announcements.index'],
        'notifications' => ['notifications.index'],
        'thread' => ['chat.show'],
        'messages' => ['chat.*', '*.orders.messages.*'],
        'search' => ['search.index'],
        'catalog' => ['buyer.for-you', 'buyer.stock.*'],
        'storefront' => ['home'],
        'login' => ['login'],
        'auth' => ['login', 'register', 'register.*', 'password.*', 'verification.*', 'rider.activation.*'],
        'form' => ['*.create', '*.edit', '*.dispute.create', 'account.profile.*'],
        'order' => ['*.orders.show'],
        'purchases' => ['buyer.dashboard'],
        'dashboard' => ['*.dashboard'],
        'table' => ['seller.products.index', '*.orders.index', '*.reports*', 'sales-reports.*', 'activity-logs.*', 'finance.*', 'coverage.*', 'parcel-transfers.*', 'buyer.addresses.*', 'admin.*', 'logistics.*', 'logistics-admin.*', 'courier.*', 'seller.*'],
    ],
];
