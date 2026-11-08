# Wallet, remittance and shipping research

Research date: 8 November 2026. Official documentation is the benchmark; no source below discloses a generally applicable Philippine rider/sorting-center revenue split.

| Source | Finding and EZiCart decision |
| --- | --- |
| [BSP Circular 1166](https://www.bsp.gov.ph/Regulations/Issuances/2023/1166.pdf) | E-money and its issuers have specific regulation. The closed-loop exclusion cannot simply be assumed for a marketplace accepting independent sellers. Keep this implementation a clearly labeled sandbox until provider, licensing and legal requirements are satisfied. |
| [BSP payments oversight](https://www.bsp.gov.ph/SitePages/PaymentsAndSettlements/PaymentsAndSettlements.aspx) and [registered OPS list](https://www.bsp.gov.ph/PaymentAndSettlement/cor.pdf) | Payment-system registration and e-money authorization are distinct questions. A provider's OPS listing does not automatically authorize EZiCart to issue transferable/withdrawable value. |
| [Shopee seller balance terms](https://help.shopee.ph/portal/4/article/77272) | Seller balance and withdrawals are separate from buyer payment. Release can depend on completion, return/refund periods and security checks. Preserve EZiCart's seven-day delivery hold and dispute/remittance checks. |
| [Shopee selling guide](https://shopee.ph/m/sell-on-shopee) | Buyer shipping payment, platform shipping promotions and seller-funded shipping promotions are distinct. Declared dimensions/address differences can affect charges. Preserve the funding source and pricing snapshot. |
| [ShopeePay terms](https://help.shopee.ph/portal/4/article/169836-ShopeePay-Terms-of-Service) | ShopeePay is a licensed financial product with verification and cash-out conditions. EZiCart's mock credits must never be represented as ShopeePay or live regulated e-money. |
| [Lazada transaction API documentation](https://lazada-sellercenter.readme.io/docs/gettranscationdetails) | Item credits, customer shipping credits, logistics shipping debits, subsidies, commissions, return fees and reversals are separately identifiable statement types. Use separate ledger entries instead of calling every buyer shipping peso profit. This is a seller API reference, not a Philippine contractual tariff. |
| [LBC COD service/calculator](https://www.lbcexpress.com/cash-on-delivery) | Price depends on weight and destination and estimates can have valuation/other charges. COD delivery includes payment collection and later merchant payment. Advertised rates are product-specific and cannot establish rider wages or hub percentages. |
| [J&T Philippines](https://www.jtexpress.ph/) | Official site was checked, but its client-rendered page exposed no auditable tariff/remittance table to the research tool. Do not substitute another country's J&T rates as Philippine contract terms. |
| [J&T Malaysia official FAQ](https://jtexpress.my/index.php/faq) | Illustrates actual versus volumetric charging and different dimensional divisors across services. This supports making the divisor configurable; its numeric divisors and prices are not asserted to apply in the Philippines. |
| [PayMongo webhook documentation](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management) | Server events need raw-body HMAC verification, mode-specific signatures, replay controls and idempotency. Browser redirects do not prove funding. A payment API alone is not authorization to issue a withdrawable wallet. |

## Proposed operating model

1. Reserve maximum promotion liability before activation. Recognize actual redemption only once. A PHP 50 cap with 100 redemptions reserves PHP 5,000; it does not immediately expense the entire PHP 5,000.
2. Keep physical COD cash accountability separate from balances and earnings. A rider's cash collection is money held for settlement, not their compensation.
3. Batch cash handover records expected, submitted and physically received amounts independently. Mismatches remain unresolved until an authorized, evidenced investigation; do not confiscate rider earnings automatically.
4. Quote shipping from an approved route/weight rate version and a greater-of-actual-and-volumetric chargeable weight. Price components cover pickup, hub handling, actual hub transport, delivery, COD/surcharges and overhead/margin. Actual beneficiary claims depend on recorded work and custody.
5. Show derived percentages from approved peso allocations for a particular shipment. Never present invented percentages as Shopee, Lazada, J&T or LBC policy.
6. Seller-funded item discounts paid from an already funded promotional account must not also be deducted a second time from seller settlement. Legacy unfunded vouchers retain their historical calculation; funded promotions add their financing back exactly once.

The user confirmed no approved cost schedule is available. Any example schedule must be explicitly sandbox-only. Live rate approval and sufficient funding remain operator responsibilities. Provider onboarding, KYC/AML, safeguarding, tax/employment treatment and regulatory review require an appropriate professional/provider before real-money operation.
