# EZiCart Wallet: school demo walkthrough

The wallet uses internal **demo credits**, including administrator-reviewed **recorded demo cash deposits**. These are simulated funds. No external payment account, cash-in gateway, bank/card charge or real cash-out is provided. The design follows familiar marketplace wallet tasks while retaining EZiCart's own interface and accounting.

## 1. Prepare the demo environment

The additive wallet migration is applied. **Demo funding was explicitly enabled on 14 November 2026 at the user's request** in the configured local/sandbox environment; no opening balances were created. The final review observed a scheduler worker, a queue worker and the every-minute wallet expiry schedule. For future reactivation in the Laravel directory, replace `ADMIN_ID` with an approved commerce administrator's ID:

```powershell
php artisan finance:sandbox enable --actor=ADMIN_ID
```

This records a configuration audit; it creates no balances. The command and funding/payment operations are restricted to `local`/`testing` with financial mode `sandbox`. Do not change a real-money deployment into a demo environment to bypass this guard. The existing withdrawal feature also remains a sandbox ledger operation.

Keep the project's scheduler running. For a Windows demo terminal, the usual Laravel scheduler worker is:

```powershell
php artisan schedule:work
```

It invokes `wallet:expire-orders` every minute. For a one-time cleanup, use `php artisan wallet:expire-orders`. A closed browser does not stop the deadline. Expired reservations reject payment immediately; stock/voucher cleanup happens when the scheduler executes. Do not stop the scheduler with unpaid reservations outstanding.

## 2. Request demo funds

1. Log in as the intended buyer, seller, rider, hub staff member or administrator and open **Wallet → Add demo funds**.
2. Select **Demo credits**, enter the demo amount and a reason, then submit. Available balance stays unchanged while the request is pending.
3. For the second funding type, select **Record demo cash deposit**. Include a unique demo receipt reference and a private JPG, PNG or PDF up to 5 MB. Use synthetic receipt details; this does not record a real cash receipt. Reusing a recorded reference is blocked.
4. Requests expire after 24 hours. Submit a new request with a new reference when appropriate rather than editing old evidence or decisions. Original request fields and completed reviews remain immutable.

Commerce administration reviews buyer/seller/platform-treasury requests. Logistics administration reviews rider/hub/logistics-administrator requests. A requester cannot approve their own request. Funding a commerce administrator's shared treasury therefore requires another approved commerce administrator. The final review found only one approved commerce administrator, so that particular treasury request needs another eligible administrator before it can be independently approved. Buyer/seller funding reviews are available now.

## 3. Review funding as an administrator

1. Open **Wallet → Add demo funds** in the appropriate administrator account. Pending funding also appears in workflow attention.
2. Review the account, exact amount, request type, reason and any private demo receipt. Another user or an administrator outside that account's role scope cannot download the evidence. A changed or missing receipt cannot be approved.
3. Choose **Approve demo funds** or **Reject request**, enter a reason and confirm.
4. Approval posts one balanced `demo_wallet_credit` transaction and notifies the requester. Replaying approval does not credit again; a conflicting later decision is refused. Rejection adds no funds.
5. Review the **Transactions** tab or exported wallet statement for the ledger reference. Available, pending earnings, reserved promotions and withdrawal holds remain separate balances.

Legacy signed mock-funding history is retained for compatibility. Those mock event controls cannot approve a new internal funding request or bypass independent review.

## 4. Buy immediately or use To Pay

1. Choose products from one shop, select them in Cart or use Buy Now, then review the address, voucher, delivery fee and total.
2. Select **EZiCart Wallet · demo**. With the reservation checkbox unchecked, the existing immediate wallet checkout still requires enough available funds and pays atomically.
3. To pay later, check **Reserve now and pay from To Pay** and place the order. Stock and the selected voucher liability are reserved once for 30 minutes. Unchecked cart items stay saved. Buy Now remains separate from the saved cart.
4. The buyer sees the order in **To Pay**, with its deadline, exact stored total, available balance and shortfall. The seller cannot prepare it and has no preparation deadline yet.
5. Add approved demo funds if needed, return to the payment page and confirm the account password. Successful payment debits the full total once, records its reference, starts the seller response deadline and moves the order to **Preparing to ship**. Repeating the payment does not charge or notify twice.
6. Frozen, unsupported, altered or incomplete financial records remain on hold. Ask administration to reconcile the original posting; another payment must not be used to clear a hold. Changed product availability or physical-custody conflicts also prevent payment.

Amounts are server-owned. Browser values, a successful-looking page and payment-method text cannot establish payment. A reserved quote keeps its recorded price and discount during the reservation window; cancelled/expired reservations do not reactivate an offer that is paused or expired.

## 5. Cancel and refund

- **Unpaid:** choose **Cancel unpaid reservation**, or use the ordinary cancellation action. It cancels immediately, restores stock and eligible voucher use once, and makes no wallet debit/refund. Expiry performs the same reservation release automatically.
- **Paid buyer cancellation:** the existing seller approval/24-hour response rules and physical-pickup cutoff remain. Requesting cancellation pauses fulfillment but does not immediately credit money. Approval returns the original full internal-wallet payment, reverses pending seller proceeds and restores eligible voucher liability/use once.
- **Seller cancellation:** a permitted pre-custody cancellation requires a reason and returns the full recorded wallet payment. It does not release seller available earnings.
- **Example:** with PHP 200 approved demo funds, a PHP 150 order leaves PHP 50 available. An approved full cancellation returns PHP 150, bringing the available balance to PHP 200. The actual checkout quote controls fees and discounts; this example does not set a tariff.

The buyer receives a notification only after a refund credit is posted. The original debit and refund remain visible as separate immutable entries. A cancellation request, return inspection and refund authorization are distinct from an executed refund.

## 6. Deliver or return a prepaid parcel

After verified payment, use the normal seller preparation, rider pickup, sorting, hub-transfer and final-delivery flow. A verified prepaid rider job shows **PHP 0.00 to collect**. Buyer code, recipient confirmation, proof photo, vehicle/capacity and custody scans remain required. Completion does not create COD collection, remittance or a second wallet payment.

For undelivered RTS, recover and return the physical parcel, complete the rider/seller handover and seller receipt, and record the inspection. Commerce administration reviews **Return progress**, authorizes the full eligible wallet refund, then uses the separate **Credit approved wallet refund** action. Authorization alone sends no money. Execution credits the buyer once; repeated execution is refused, and seller available proceeds stay blocked. Inspection controls restocking separately from the financial refund; damaged units are not automatically restored to stock.

## 7. End the demo

Resolve or cancel unpaid reservations and reconcile any pending funding/refunds. Disable new demo operations with the existing control:

```powershell
php artisan finance:sandbox disable --actor=ADMIN_ID
```

If the server environment explicitly forces the sandbox on, its configuration also needs updating by the operator. Disabling funding does not erase balances, payment/return history or refunds. Retain those records for the audit. No teacher workbook checkbox is changed by this walkthrough.
