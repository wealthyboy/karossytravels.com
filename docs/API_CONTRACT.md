# Karossy API contract

The website and mobile application consume the same versioned JSON API. Neither client communicates with an inventory source directly.

## Response envelope

Successful responses use:

```json
{
  "data": {},
  "meta": {
    "api_version": "v1",
    "request_id": "uuid"
  }
}
```

`X-Request-ID` is also returned as a response header. Clients should include it in support reports. A valid incoming `X-Request-ID` is preserved, allowing a request to be traced from the website or app through Laravel logs and inventory calls.

## Client initialization

`GET /api/v1/app/bootstrap`

Returns shared service availability, feature flags, support contacts and the default currency. This avoids hard-coding operational settings independently in the website and mobile app.

## Money

All monetary amounts use integer minor units, for example `52500000` represents `NGN 525,000.00`. Clients format money for display but never perform booking calculations using floating-point numbers.

### Display currency selection

Laravel is the source of truth for both the converted amount and its ISO currency. The website and mobile app must format the currency returned beside each amount; clients must not independently calculate currency conversions.

The server resolves display currency in this order:

1. A currency explicitly selected by the visitor and stored in the session.
2. The saved preference of an authenticated customer.
3. The visitor country supplied by the hosting platform or resolved from the request IP.

Visitors detected in Nigeria default to `NGN`. Every other country defaults to `USD`. If country detection is unavailable, the safe fallback is `USD`. An explicit currency-switcher choice always takes precedence over location detection.

## Supplier isolation

- Public offer IDs are Karossy identifiers.
- Inventory offer identifiers, access tokens and credentials remain server-side.
- Raw inventory responses are normalized and never returned directly to clients.
- Fare validation is mandatory immediately before reservation creation.

## Search and offer lifecycle

`POST /api/v1/flights/search` persists a search and returns `meta.search_id`. Each result has a Karossy offer UUID and an `expires_at` timestamp. Provider references are stored server-side and are never accepted from a client.

Offer prices contain integer minor-unit values for base fare, taxes, Karossy markup and the final selling total. Markups default to zero until commercial rules are approved. Expired offers must be searched again; a future validation endpoint will reprice a selected offer before an order can be created.

## Commerce boundaries

- An **order** is Karossy's commercial record and may eventually contain multiple travel products.
- A **booking** is a reservation held by a provider and may have a locator/PNR.
- A **payment** records money movement independently of booking success.
- A **ticket** records issuance, void and refund state independently of the booking.

Traveller and customer snapshots are encrypted at rest. Card details must never be stored in these records.

## Live inventory activation gate

Live search and booking remain disabled until the required REST authentication grant, product entitlements, certification workflow, rate limits, ticketing permissions and cancellation/refund sequence are confirmed. Credentials belong only in `.env`; never send them to a web or mobile client.
