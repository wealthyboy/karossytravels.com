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

Mobile clients send `X-Client-Platform: mobile` and their selected `currency` as a query parameter. `data.homepage.flight_offers` contains up to four published, future-dated homepage offers from the admin. The same list is available from `GET /api/v1/flights/promotions?currency=NGN` as `data.offers`.

Each homepage card includes its image, route, one featured airline, exact departure/return dates, a converted `price` with `is_estimate: true`, optional `promotion`, and `search_criteria`. Submit those search criteria to the flight search endpoint with a new `session_id`; do not generate replacement travel dates or force the featured airline onto the search. Eligibility is calculated server-side for each live fare. Cards may feature one airline while their promotion applies to multiple eligible airlines or all airlines.

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

Offer prices contain integer minor-unit values for base fare, taxes, Karossy pricing adjustments and the final selling total. Expired offers must be searched again. `GET /api/v1/flights/offers/{id}?currency=NGN` revalidates the selected fare and returns `data.offer` plus `data.validation.price_changed`.

### Flight discounts and checkout

Search, review and online checkout use the same price breakdown:

```json
{
  "currency": "NGN",
  "total_minor": 44887500,
  "original_total_minor": 52500000,
  "discount_minor": 7612500,
  "promotion_discount_minor": 4987500,
  "markdown_minor": 2625000,
  "markup_minor": 0,
  "base_minor": 45000000,
  "taxes_minor": 7500000
}
```

`original_total_minor` is the final total plus the displayed savings. `discount_minor` is `markdown_minor + promotion_discount_minor`; a configured general markdown and an eligible flight promotion can both apply. These fields describe the selling-price breakdown, not new fees added at checkout. Clients must use the returned amounts, including the returned currency if conversion is unavailable.

An eligible offer also has `promotion` with `id`, `type`, `percent`, converted `discount_minor`, `airlines_scope`, `eligible_airline_codes`, booking/travel date windows and `timezone`. Ineligible/expired promotions return `null`. The promotion ID is informational: clients cannot set discounts or override selling prices.

`POST /api/v1/flights/offers/{id}/checkout` accepts traveller/contact details, accepted `terms`, selected `currency`, and `expected_total_minor` equal to the total currently shown. Send this field in new mobile builds. The server independently revalidates and prices the offer. If the supplier fare/promotion changed or the submitted expected total differs, it responds with HTTP `409`:

```json
{
  "message": "The fare changed. Please review the updated total before paying.",
  "error_code": "FARE_CHANGED",
  "data": { "offer": { "id": "offer-uuid", "price": {}, "promotion": null } },
  "meta": { "api_version": "v1", "request_id": "request-uuid" }
}
```

The example omits the updated price/itinerary fields for brevity. No payment is initialized on this conflict. Preserve customer details, replace the displayed offer with `data.offer`, and wait for the customer to review and explicitly submit again. A successful online checkout returns HTTP `201` with the final `price`, optional `promotion`, payment reference, authorization URL and checkout token. Preserve the token for payment-status requests; never charge again to resolve a booking-confirmation delay.

Display currencies and payment currencies are not interchangeable. This mobile checkout accepts `NGN` and `USD`; GBP/EUR remain display options. The app directs customers to switch the search-results currency before payment instead of silently changing the currency or submitting an unsupported Paystack charge. USD also requires the merchant's USD capability to be enabled. See [Paystack supported currencies](https://paystack.com/docs/api/).

### Child ages

For hotel searches, show one age selector immediately for every selected child. Options are `0`–`17`, with `0` displayed as **Under 1**. Submit `children_ages` as real integers, for example `children: 2, children_ages: [0, 8]`; require exactly one selected age for each child. Do not infer ages or classify hotel infants independently. Laravel passes the actual ages to Sabre in its configured occupancy format. Flight searches also accept/validate child ages for their configured traveller rules.

## Customer service enquiries

`GET /api/v1/enquiries/options` returns the shared website option lists:

- `data.car_hire`: `hire_types`, `return_required`, `vehicle_preferences`.
- `data.visa_enquiry`: `countries`, `visa_types` keyed by purpose, `document_questions` keyed by purpose, `occupations`, `timelines`, `duration_units`.

The mobile forms use these lists, including purpose-specific visa types, searchable countries and supporting-document questions. Do not replace the client enquiry form with the vehicle-partner registration or the old visa eligibility search.

`POST /api/v1/enquiries/car-hire` accepts the client's car-hire form fields: `full_name`, `email`, `phone`, `pickup_location`, `dropoff_location`, `pickup_date` (`YYYY-MM-DD`), `pickup_time` (`HH:mm`), `hire_type`, `vehicle_preference`, `passengers`, optional `luggage`, `return_end_date`, `return_end_time`, `additional_requirements`. Return date/time are required for the configured return-required hire types or when either return value is supplied; return must follow pick-up.

After a car-hire request is successfully saved, the shared website/mobile submission service sends a thank-you and receipt acknowledgement to the submitted customer email, with `willie.samson@karossytravels.com` in BCC. It includes the enquiry reference and explains that vehicle availability and booking are not yet confirmed. HTML and plain-text versions use the existing configured mailer; no queue worker is required. A mail delivery failure is logged by reference only and does not undo the saved request or change the successful API response. Driver-partner registrations are unchanged.

`POST /api/v1/enquiries/visa` accepts `full_name`, `email`, `phone_whatsapp`, `nationality`, `country_of_residence`, `destination_country` (country names from the options endpoint), `purpose_of_travel`, `visa_type`, `proposed_travel_date`, `duration_number`, `duration_unit`, `number_of_applicants`, `occupation_status`, `previous_travel`, `previous_refusal`, `valid_passport`, `holds_valid_visa`, `supporting_document`, `application_timeline`, optional `additional_information`. The five yes/no answers use literal `Yes` or `No`, not booleans. `countries_previously_visited` and `refusal_details` apply only when their corresponding answers are `Yes`.

Successfully saved visa enquiries also send a thank-you and receipt acknowledgement to the submitted customer email, BCC'ing `willie.samson@karossytravels.com`, on both website and mobile. The HTML/plain-text email includes the enquiry reference and explains that the Visa Team will follow up with requirements, fees and next steps. It does not include visa-background answers or documents, and does not imply approval or application submission. Delivery failures preserve the saved enquiry, as for car hire. Existing paid visa-application confirmations are unchanged.

Both submission endpoints share the website FormRequests and persistence service. Successful submissions return HTTP `201` with `data.reference` and `data.message`, appear in the existing admin enquiry inbox, and do not echo contact details or background answers. Trip/background details remain encrypted; operational logs contain only the enquiry reference. Validation failures return HTTP `422` with Laravel's field `errors`; preserve entered values and show these messages. Submission endpoints are limited to eight requests per minute. There is no enquiry receipt or success screen until the API confirms persistence.

### Production client-testing offers

After deploying migrations and code, an authorized operator can deliberately replace homepage offers with the four testing fixtures:

```sh
php artisan db:seed --class=FlightOfferTestSeeder --force
```

Production runs without `--force` are refused. Existing homepage offers and affected linked deals are backed up before replacement. The fixtures are public, not client-only, and do not switch live supplier/payment configuration to a sandbox. See [flight promotion testing notes](flight-promotions.md) before running this on production.

## Commerce boundaries

- An **order** is Karossy's commercial record and may eventually contain multiple travel products.
- A **booking** is a reservation held by a provider and may have a locator/PNR.
- A **payment** records money movement independently of booking success.
- A **ticket** records issuance, void and refund state independently of the booking.

Traveller and customer snapshots are encrypted at rest. Card details must never be stored in these records.

## Live inventory activation gate

Live search and booking remain disabled until the required REST authentication grant, product entitlements, certification workflow, rate limits, ticketing permissions and cancellation/refund sequence are confirmed. Credentials belong only in `.env`; never send them to a web or mobile client.
