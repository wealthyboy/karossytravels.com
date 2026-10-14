# Flight promotions

In **Admin → Flight offers**, edit a card and enable **Apply a live-fare discount for this route**.

- Set the route using exact airport codes, e.g. LOS → LHR. The reverse route and LGW are not included.
- Choose one **Featured airline** for the homepage card. It must be covered by the promotion.
- Select one or more discounted airlines, or **All airlines**. Airline matching uses the supplier's validating airline, not connecting/operating carriers.
- Enter the percentage, e.g. 10 for 10%. New promotions use percentages only; existing fixed-amount deals keep their old behavior until edited.
- Set a **Promotion booking window** for a sale such as Wednesday through Sunday. Dates are inclusive in Africa/Lagos time.
- Optionally set an **Eligible departure window**. If both windows exist, both must match. The return date is not restricted.

The starting price entered on a card is the indicative customer fare **before the promotional discount**, including any normal markup already accounted for by the administrator. Matching discounts are deducted before display-currency conversion. It remains an estimate, not a live supplier quote.

The same rule applies to qualifying live searches regardless of whether the customer clicked the card. Live fares receive the promotion after normal markup/markdown and before currency conversion. The provider fare and request payload are unchanged. Revalidation recalculates from the current supplier fare, so the discount is not deducted twice. If a promotion expires or changes before payment, the customer must accept the newly confirmed price.

Overlapping deals do not stack: highest priority wins, then the greatest actual saving, then the oldest rule ID. Draft, disabled or deleted homepage offers do not leave their linked promotion running. Standalone rules remain available in **Admin → Flight deals**; linked cards and that screen share the same stored rule.

For deployment, apply `database/migrations/2026_10_14_000003_extend_flight_promotions.php` before serving the updated code. Existing offers and legacy rules are preserved. Do not seed demonstration promotions into the live database.

Regression coverage: `tests/Feature/FlightPromotionsTest.php` includes multi-airline/all-airline matching, route/date exclusions, Lagos midnight, overlap priority, currency conversion, administration validation, repeated revalidation and payment initialization. Supplier and payment requests in these tests are fake.

## Four checkout test cards

Run explicitly on a local/test installation with the flight-promotion migration and existing flight-search `children_ages` migration applied:

```sh
php artisan db:seed --class=FlightOfferTestSeeder
```

This replaces only the homepage flight cards and deactivates their old linked promotions. It does not drop tables, delete uploaded images, erase live search/booking/payment records, alter default pricing/currency settings or disable unrelated standalone deals. A verified JSON backup of the previous cards and linked rules is saved in `storage/app/private/flight-offer-test-backups/` before replacement. The seeder refuses production and aborts without replacement if prerequisites or backup verification fail. It is not included in the normal `DatabaseSeeder`.

The new cards are LOS → LHR (QR/TK, 10%), LOS → DXB (all airlines, 12%), LOS → CDG (AF, 15%) and LOS → DOH (QR, 5%). Each shows only its featured airline. They use economy round trips, USD indicative starting prices, future departures, a 30-day booking window and a 7–60-day departure window, all relative to the day the seeder runs. Unrelated overlapping discounts are preserved; the test rules use a higher priority. The cards are labelled TEST so they are not mistaken for verified live quotes.

Click a card, select a qualifying live validating airline, and proceed to checkout. The route, travel dates and cabin go into the normal search; the percentage is applied server-side and saved on the live offer through revalidation and payment initialization. A non-qualifying airline does not receive the discount. The actual live total can differ from the card estimate. Stop before real payment/ticketing unless you intend to make a booking. Re-running refreshes the four cards and dates, preserving old rule IDs for historical records.
