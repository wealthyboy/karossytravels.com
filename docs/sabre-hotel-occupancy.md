# Hotel child ages

Verified against [Sabre Get Hotel Avail v5 reference](https://developer.sabre.com/rest-api/get-hotel-avail/v5.0/_attachments/spec.yml), Room schema, on 14 October 2026.

`GetHotelAvailRQ.SearchCriteria.RateInfoRef.Rooms.Room` is an array of room objects. Each room has an integer `Index`, integer `Adults`, optional integer `Children`, and a **string** `ChildAges`. When children are present, `ChildAges` must contain their individual ages separated by commas.

Example for one room, two adults and children aged under one and seven:

```json
{"Index": 1, "Adults": 2, "Children": 2, "ChildAges": "0,7"}
```

The website accepts ages 0–17 and displays 0 as “Under 1”. It sends 0 unchanged and does not reclassify guests locally. Sabre and the hotel determine eligibility and pricing.

The v5 schema requires matching adult and child counts across requested rooms. The UI explains this constraint and labels each child's room. Ages are grouped in displayed room order; they are not repeated for every room. Different room occupancies require separate searches.

Price check continues to use the opaque supplier `RateKey` that identifies the selected stay and occupancy. The flight-search payload is not changed by this hotel work.
