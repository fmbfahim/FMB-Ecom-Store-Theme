# OrderFlow v2.7.0 Feature Integration Plan for Theme `fmb-ecom-store`

Porting all major features from `OrderFlow v2.7.0` plugin into the custom WordPress e-commerce theme `fmb-ecom-store` (`d:/Ghor Sajao/Website Plugins/fmb-ecom-store`).

---

## Overview of Features to Integrate

1. **Anti-Fraud & Checkout Restrictions System**
   - Phone number length & format validation (BD 11-digit check).
   - Blacklist blocking (Phone, Email, IP Address, Device Fingerprint).
   - Order limit enforcement (e.g. max X orders per phone/IP within 24 hours).
   - Device & Hardware fingerprinting (`_ads_device_hash`, `_ads_hardware_hash`).
   - VPN / Proxy Shield check.

2. **BD Courier Integration Engine**
   - Support for **Steadfast**, **Pathao**, **RedX**, **Paperfly**.
   - Delivery Success Rate Checker (BD Courier Check API) to verify customer fraud history.
   - Courier Webhook Handlers for automatic status updates.
   - Courier Remarks Extractor & Order Meta Box in Admin UI (`inc/admin-pages/order-manager.php`).

3. **Courier Rate & Partial Payment Engine**
   - Courier success rate threshold trigger (e.g., if success rate < 70%, force partial advance payment via bKash/Nagad/Rocket).
   - Partial payment breakdown display on checkout & order emails.
   - Dynamic total calculation for manual gateway payment of delivery charge/advance.

4. **Smart SMS & OTP Verification System**
   - Integration with Bangladeshi SMS Gateways (BulkSMSBD, Greenweb, ElitBuzz, SMSPoh, AlphaSMS, etc.).
   - OTP Verification popup/field on checkout before order placement.
   - Automated SMS triggers for order placement, status changes, and shipping updates.

5. **Server-Side & Client-Side CAPI Analytics (Facebook, TikTok, Google)**
   - **Facebook Conversions API (CAPI)** + Browser Pixel with event deduplication (`event_id`).
   - **TikTok Server-Side Event API** + Browser Pixel.
   - **Google GA4 & Google Ads GTAG** + Server-Side Event API.
   - Custom event management and conversion tracking.

6. **Incomplete Orders & Abandoned Checkout Recovery**
   - Real-time field capture on checkout input change (stores partial name, phone, address, cart contents in `ads_incomplete_orders_tracker` DB table).
   - Recovery options (WhatsApp / SMS link) and admin dashboard view.

7. **Custom Order Statuses & Order Amount Modifiers**
   - Custom WooCommerce order statuses (`wc-incomplete`, `wc-partial-paid`, `wc-fake-order`, `wc-follow-up`).
   - Order bump / shipping charge adjusters.

---

## User Review Required

> [!IMPORTANT]
> - Database tables (`wp_ads_customers_data`, `wp_ads_incomplete_orders_tracker`) will be auto-created on theme setup or admin init.
> - All settings will be neatly organized under the theme's admin panel (`Theme Settings` / `OrderFlow Features`).
> - We will structure the integration inside `inc/orderflow/` so the theme remains clean and modular.

---

## Proposed Changes

### Theme Base Setup
#### [MODIFY] [functions.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/functions.php)
- Include OrderFlow master initializer `require_once get_template_directory() . '/inc/orderflow/init.php';`.

---

### Core OrderFlow Modules (`inc/orderflow/`)

#### [NEW] [init.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/inc/orderflow/init.php)
- Main loader initializing database tables, courier engine, anti-fraud, SMS, CAPI tracking, and incomplete order tracker.

#### [NEW] [class-checkout-restrictions.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/inc/orderflow/class-checkout-restrictions.php)
- Checkout validation hooks, blacklisting, device fingerprinting, order rate limit per phone/IP, and VPN modal.

#### [NEW] [class-bd-courier-engine.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/inc/orderflow/class-bd-courier-engine.php)
- Steadfast, Pathao, RedX, Paperfly APIs, delivery success rate check, webhook endpoints, and admin status sync.

#### [NEW] [class-partial-payment.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/inc/orderflow/class-partial-payment.php)
- Courier rate threshold rules, partial payment requirements for low success rate users, and checkout cart total modifiers.

#### [NEW] [class-smart-sms.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/inc/orderflow/class-smart-sms.php)
- BD SMS Gateways API driver, OTP generation & checkout validation modal, order notification triggers.

#### [NEW] [class-capi-tracker.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/inc/orderflow/class-capi-tracker.php)
- Facebook CAPI, TikTok Server API, and Google GTAG/GA4 Server API event handlers.

#### [NEW] [class-incomplete-orders.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/inc/orderflow/class-incomplete-orders.php)
- Real-time ajax checkout field capture, abandoned cart recovery database tracker.

#### [NEW] [class-admin-settings.php](file:///d:/Ghor%20Sajao/Website%20Plugins/fmb-ecom-store/inc/orderflow/class-admin-settings.php)
- Admin configuration UI integrated into the theme settings panel.

---

## Verification Plan

### Manual Verification
1. **Admin Panel Verification**: Verify that OrderFlow settings (Couriers, SMS Gateway, Blacklist, CAPI keys, Partial Payment rules) appear in WordPress WP-Admin under theme settings.
2. **Checkout Validation**: Test placing an order with invalid/blacklisted phone number, test OTP flow, and test device fingerprint / order rate limit.
3. **Incomplete Order Capture**: Type customer info in checkout without submitting and verify it captures into the abandoned checkout table.
4. **Courier API Check**: Verify delivery history lookup and courier dispatch options.
