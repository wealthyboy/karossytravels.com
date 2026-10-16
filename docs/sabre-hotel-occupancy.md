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

## CSL booking room indices

The room index in CreatePassengerNameRecord v2.5 booking requests is a separate field from both the availability `Index` and the guest `Index`. Following [Sabre's CSL booking and multi-room guidance](https://developer.sabre.com/rest-api/create-passenger-name-record/2.3.0/help-documentation/createpassengernamerecordrq.html), each `CreatePassengerNameRecordRQ.HotelBook.Rooms.Room` object includes an integer `RoomIndex`, numbered from 1. A single-room booking still sends `RoomIndex: 1`.

The booking builder emits one room object per requested room, with sequential indices and the supplied lead guest. `NumberOfRooms` matches the emitted room count. The supplier BookingKey is passed through unchanged; this does not re-price the stay or change its selected occupancy.

Sabre may return an unsuccessful `ApplicationResults` inside an HTTP-success response. Incomplete/NotProcessed responses and explicit application errors must not create a confirmed local order, even if a locator is included. The full saved response and its error/warning messages remain available to authorised staff in operational logs; the public checkout keeps its safe payment-under-review message. A completed response containing informational warnings is not rejected solely for having warnings.

The room-index regression was identified from `NGHP.ERR.0767: Invalid room stay index`. Tests use fake HTTP responses only. This fix does not automatically retry paid attempts, charge customers, or create supplier reservations; a fresh certification booking is still required to establish supplier acceptance.
