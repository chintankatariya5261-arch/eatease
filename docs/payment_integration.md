# EatEase Payment Integration

## Overview
- Uses Razorpay Checkout for secure payments with cards, netbanking, UPI, and wallets.
- Server creates orders, verifies signatures, and updates payment status.
- Webhook endpoint processes asynchronous events for reliability.

## Setup
- Create `.env` at project root with:
  - `RAZORPAY_KEY_ID=your_key_id`
  - `RAZORPAY_SECRET=your_secret`
  - `RAZORPAY_WEBHOOK_SECRET=your_webhook_secret`
- Configure Razorpay Dashboard:
  - Enable payment methods (cards, UPI, netbanking, wallets).
  - Add webhook pointing to `https://your-domain/payments/webhook.php` with events `payment.captured`, `payment.failed`, `order.paid`.

## Configuration
- `config/config.php` loads secrets from `.env`.
- Do not hardcode secrets in source control.
- Ensure HTTPS on production.

## Checkout Flow
- User triggers payment in `profile/my-bookings.php`.
- Server:
  - `payments/create.php`: validates user/booking, creates `payments` record, creates Razorpay order, responds with order details.
  - `payments/verify.php`: verifies signature after checkout, marks payment captured.
- Webhook:
  - `payments/webhook.php`: verifies `X-Razorpay-Signature`, updates payment status by `provider_order_id`.

## APIs
- Create Order: `POST payments/create.php` with `booking_id`.
- Verify Payment: `POST payments/verify.php` with:
  - `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `payment_id`, `booking_id`.
- Webhook: `POST payments/webhook.php` with Razorpay event payload and `X-Razorpay-Signature`.

## Error Handling
- Validates session, booking ownership, and request payloads.
- Handles database and network errors with JSON responses.
- Idempotent verification avoids duplicate capture updates.

## Testing
- CLI tests:
  - `php tests/payments/VerifySignatureTest.php`
  - `php tests/payments/WebhookSignatureTest.php`
- Set `.env` variables before running tests.

## PCI DSS
- No storage of card PAN, CVV, or sensitive auth data.
- Uses Razorpay hosted fields and Checkout for card entry.
- Enforce HTTPS, secure cookies, CSRF protection for non-payment endpoints.
- Restrict access to secrets and rotate periodically.
- Monitor webhook integrity and log event processing.

## Notes
- Amount is currently fixed per booking; adjust as per business logic.
- Ensure server timezone and currency settings match business requirements.
