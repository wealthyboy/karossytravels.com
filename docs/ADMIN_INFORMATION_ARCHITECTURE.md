# Karossy Operations Information Architecture

## Flights

- Search Flights: live inventory shopping and booking entry point.
- Fare Rules: airline-specific rules plus global Karossy booking conditions. Checkout resolves both automatically using the validating airline.
- Add-ons: optional flight services sold during checkout.
- Operational Logs: searchable request lifecycle from search through revalidation and booking.

## Hotels

- Search Hotels: provider hotel shopping.
- Add-ons: optional hotel services such as transfers, breakfast upgrades or support packages.
- Operational Logs: hotel search and booking request lifecycle.

## Visas

- Visa Services: countries, visa types, fees and processing times.
- Applications: customer applications and their workflow status.
- Requirements: reusable document and eligibility requirements.

## Bookings

- All Bookings, Flight Bookings, Hotel Bookings and Visa Bookings.
- Source Attribution identifies website, mobile, B2B, administrator, affiliate and campaign sources.

## Analytics

- Executive Overview: gross booking value, revenue, profit, bookings and ticketing health.
- Revenue & Profit: provider cost, markup, discounts, fees and margin.
- Booking Performance: confirmed, pending, failed, cancelled, refunded and ticketed.
- Search & Conversion: searches, result quality, offer selection, checkout and booking conversion.
- Routes & Destinations: demand, conversion and revenue by origin/destination.
- Airline Performance: sales, margin, cancellations, revalidation failures and ticketing success.
- Hotel Performance: searches, conversion, destinations, room nights and margin.
- Customer Retention: new/returning customers, repeat rate and lifetime value.
- Channel Attribution: B2C, B2B, admin, mobile, affiliate and campaign performance.
- Provider & API Health: latency, errors, empty responses and availability.
- Ticketing Operations: pending issuance, failed issuance, schedule changes and manual review.

Operational logs and analytics are deliberately separate. Logs support investigation and customer service. Analytics aggregates safe metrics for business decisions. Secrets, tokens, payment data and full passport numbers must never be stored in operational logs.
