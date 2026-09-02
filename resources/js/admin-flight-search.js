import { Modal, Offcanvas } from 'bootstrap';
import { majorAirports } from './airports';

const page = document.querySelector('[data-admin-flight-search-page], [data-public-flight-results-page]');

if (page) {
    const isPublicResults = page.matches('[data-public-flight-results-page]');
    const searchRoot = page.querySelector('[data-flight-search]')
        || (isPublicResults ? document.querySelector('.results-inline-search [data-flight-search]') : null);
    const form = searchRoot?.querySelector('form') || null;
    const submit = form?.querySelector('.flight-search-submit') || null;
    const message = page.querySelector('.flight-search-message');
    const results = page.querySelector('.flight-results');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const searchUrl = page.dataset.searchUrl || '/admin/flights/search';
    const charterContact = page.dataset.charterContact || '';
    const reviewUrlTemplate = page.dataset.reviewUrlTemplate || '';
    const revalidateUrlTemplate = page.dataset.revalidateUrlTemplate || '';
    const publicSearchModalElement = document.querySelector('#publicFlightSearchModal');
    let offers = [];
    let criteria = {};
    let summaries = [];
    let filtersBound = false;
    const publicSearchModal = publicSearchModalElement ? Modal.getOrCreateInstance(publicSearchModalElement) : null;

    const closePublicSearchModal = async startedAt => {
        if (!publicSearchModalElement || !publicSearchModal) return;

        // Very fast empty/error responses can finish while Bootstrap is still
        // completing the opening transition. Give every outcome the same short
        // display window, then dismiss the exact instance that was opened.
        const remaining = Math.max(0, 650 - (Date.now() - startedAt));
        if (remaining) await new Promise(resolve => window.setTimeout(resolve, remaining));
        publicSearchModal.hide();
    };

    const airlineNames = {
        AF: 'Air France', AT: 'Royal Air Maroc', BA: 'British Airways', DL: 'Delta Air Lines',
        EK: 'Emirates', ET: 'Ethiopian Airlines', KQ: 'Kenya Airways', LH: 'Lufthansa',
        LO: 'LOT Polish Airlines', MS: 'EgyptAir', P4: 'Air Peace', QR: 'Qatar Airways',
        TK: 'Turkish Airlines', UA: 'United Airlines', VS: 'Virgin Atlantic', WB: 'RwandAir',
    };
    const airportNames = Object.fromEntries(majorAirports.map(airport => [airport.code, `${airport.city}, ${airport.country}`]));
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
    const airlineLogo = code => `https://pics.avs.io/80/80/${encodeURIComponent(String(code || '').toUpperCase())}.png`;
    const airlineLogoMarkup = (code, compact = false) => `<span class="airline-logo-wrap ${compact ? 'compact' : ''}"><img src="${airlineLogo(code)}" alt="${escapeHtml(airlineNames[code] || code)} logo" loading="lazy" data-airline-logo><b>${escapeHtml(code || '—')}</b></span>`;
    const money = (minor, currency) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: currency || 'NGN', maximumFractionDigits: 0 }).format(Number(minor || 0) / 100);
    const time = (value) => new Intl.DateTimeFormat('en-NG', { hour: 'numeric', minute: '2-digit' }).format(new Date(value));
    const dateTime = (value) => new Intl.DateTimeFormat('en-NG', { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(value));
    const duration = (minutes) => `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
    const sessionId = () => crypto.randomUUID?.() || `00000000-0000-4000-8000-${Date.now().toString().padStart(12, '0').slice(-12)}`;

    const outboundSegments = (offer) => {
        if (criteria.trip_type === 'multi_city') return offer.segments;
        const selected = [];
        for (const segment of offer.segments) {
            selected.push(segment);
            if (segment.destination === criteria.destination) break;
        }
        return selected.length ? selected : offer.segments;
    };

    const summarize = (offer) => {
        const segments = outboundSegments(offer);
        const first = segments[0];
        const last = segments.at(-1);
        const elapsed = first && last ? Math.max(0, Math.round((new Date(last.arrival_at) - new Date(first.departure_at)) / 60000)) : 0;
        const segmentDuration = segments.reduce((total, segment) => total + Number(segment.duration_minutes || 0), 0);
        const stops = Math.max(0, segments.length - 1) + segments.reduce((total, segment) => total + Number(segment.stops || 0), 0);
        const mainAirline = offer.validating_airline || first?.marketing_airline || null;
        const airlines = mainAirline ? [mainAirline] : [];
        return {
            offer, segments, first, last, stops, airlines,
            durationMinutes: elapsed || segmentDuration,
            price: Number(offer.price?.total_minor || 0),
            departureHour: first ? new Date(first.departure_at).getHours() : 0,
        };
    };

    const groupItineraries = (items) => {
        const grouped = new Map();
        items.forEach(item => {
            const key = item.segments.map(segment => [segment.origin, segment.destination, segment.departure_at, segment.arrival_at, segment.flight_number].join('|')).join('::');
            if (!grouped.has(key)) grouped.set(key, []);
            grouped.get(key).push(item);
        });

        return [...grouped.values()].map((fares, index) => {
            fares.sort((a, b) => a.price - b.price);
            const cheapest = fares[0];
            return {
                ...cheapest,
                index,
                fares: fares.map(item => item.offer),
                refundable: fares.some(item => item.offer.refundable),
            };
        });
    };

    const stopGroup = stops => stops >= 2 ? '2' : String(stops);
    const stopLabel = stops => stops === 0 ? 'Nonstop' : `${stops} ${stops === 1 ? 'stop' : 'stops'}`;
    const minPrice = list => list.length ? Math.min(...list.map(item => item.price)) : null;
    const checkedValues = name => [...results.querySelectorAll(`input[name="${name}"]:checked`)].map(input => input.value);

    const itineraryLegs = offer => {
        const legs = new Map();
        offer.segments.forEach(segment => {
            const key = Number(segment.leg_index || 0);
            if (!legs.has(key)) legs.set(key, []);
            legs.get(key).push(segment);
        });
        return [...legs.values()];
    };

    const legSummary = segments => {
        const first = segments[0];
        const last = segments.at(-1);
        const elapsed = Math.max(0, Math.round((new Date(last.arrival_at) - new Date(first.departure_at)) / 60000));
        const stops = Math.max(0, segments.length - 1) + segments.reduce((total, segment) => total + Number(segment.stops || 0), 0);
        return { first, last, elapsed, stops };
    };

    const dealsOverview = currency => {
        if (!isPublicResults || !summaries.length) return '';
        const cheapest = [...summaries].sort((a, b) => a.price - b.price)[0];
        const fastest = [...summaries].sort((a, b) => a.durationMinutes - b.durationMinutes)[0];
        const earliest = [...summaries].sort((a, b) => new Date(a.first.departure_at) - new Date(b.first.departure_at))[0];
        const airlineDeals = [...new Set(summaries.flatMap(item => item.airlines))]
            .map(code => ({
                code,
                prices: ['0', '1', '2'].map(group => minPrice(summaries.filter(item => item.airlines.includes(code) && stopGroup(item.stops) === group))),
            }))
            .sort((a, b) => Math.min(...a.prices.filter(Number.isFinite)) - Math.min(...b.prices.filter(Number.isFinite)));

        return `<section class="flight-deals-overview" aria-labelledby="flight-deals-title">
            <div class="flight-deals-topline"><p><strong>${offers.length}</strong> live fare${offers.length === 1 ? '' : 's'} found</p><div class="flight-deal-highlights"><div class="active"><small>Cheapest</small><strong>${money(cheapest.price, currency)}</strong></div><div><small>Fastest</small><strong>${duration(fastest.durationMinutes)}</strong></div><div><small>Earliest</small><strong>${time(earliest.first.departure_at)}</strong></div></div></div>
            <details class="flight-deals-disclosure"><summary><span><small>Compare at a glance</small><strong id="flight-deals-title">Best deals by airline</strong><em>${escapeHtml(criteria.origin)} → ${escapeHtml(criteria.destination)} · By number of stops</em></span><b>View comparison <i class="bi bi-chevron-down"></i></b></summary><div class="flight-deals-scroll"><table class="flight-deals-table"><thead><tr><th scope="col">Stops</th>${airlineDeals.map(item => `<th scope="col">${airlineLogoMarkup(item.code, true)}<span>${escapeHtml(airlineNames[item.code] || item.code)}</span></th>`).join('')}</tr></thead><tbody>${['0', '1', '2'].map((group, index) => `<tr><th scope="row">${index === 0 ? 'Nonstop' : index === 1 ? '1 stop' : '2+ stops'}</th>${airlineDeals.map(item => `<td>${item.prices[index] === null ? '<span>—</span>' : `<button type="button" data-deal-airline="${escapeHtml(item.code)}" data-deal-stops="${group}">${money(item.prices[index], currency)}</button>`}</td>`).join('')}</tr>`).join('')}</tbody></table></div></details>
        </section>`;
    };

    const filterShell = () => {
        const stopFacets = ['0', '1', '2'].map(group => {
            const matches = summaries.filter(item => stopGroup(item.stops) === group);
            return { group, count: matches.length, from: minPrice(matches) };
        });
        const airlineFacets = [...new Set(summaries.flatMap(item => item.airlines))].map(code => {
            const matches = summaries.filter(item => item.airlines.includes(code));
            return { code, count: matches.length, from: minPrice(matches) };
        }).sort((a, b) => (airlineNames[a.code] || a.code).localeCompare(airlineNames[b.code] || b.code));
        const prices = summaries.map(item => item.price);
        const ceiling = prices.length ? Math.max(...prices) : 0;
        const floor = prices.length ? Math.min(...prices) : 0;
        const currency = offers[0]?.price?.currency || criteria.currency || 'NGN';

        results.innerHTML = `<button class="mobile-flight-filter-trigger" type="button" data-open-mobile-filters><i class="bi bi-sliders"></i><span>Filter</span></button><button class="mobile-flight-filter-overlay" type="button" aria-label="Close filters" data-close-mobile-filters></button><div class="admin-flight-results">
                <aside class="flight-filter-panel">
                    <div class="mobile-flight-filter-header"><strong>Filter flights</strong><button type="button" aria-label="Close filters" data-close-mobile-filters><i class="bi bi-x-lg"></i></button></div>
                    <div class="flight-price-watch"><div><strong>Watch prices</strong><small>Get notified when fares change</small></div><label class="filter-switch"><input type="checkbox"><span></span></label></div>
                    <div class="flight-filter-heading"><h2>Filter by</h2><button type="button" data-clear-flight-filters>Clear</button></div>
                    <fieldset><legend>Stops <span>From</span></legend>${stopFacets.map(item => `<label class="flight-filter-check ${item.count ? '' : 'disabled'}"><input type="checkbox" name="stops" value="${item.group}" ${item.count ? '' : 'disabled'}><span>${item.group === '0' ? 'Nonstop' : item.group === '1' ? '1 stop' : '2+ stops'} <small>(${item.count})</small></span><b>${item.from === null ? '—' : money(item.from, currency)}</b></label>`).join('')}</fieldset>
                    <fieldset><legend>Airlines <span>From</span></legend><div class="flight-airline-filters">${airlineFacets.map(item => `<label class="flight-filter-check flight-airline-filter-check"><input type="checkbox" name="airlines" value="${escapeHtml(item.code)}">${airlineLogoMarkup(item.code, true)}<span>${escapeHtml(airlineNames[item.code] || item.code)} <small>(${item.count})</small></span><b>${money(item.from, currency)}</b></label>`).join('')}</div></fieldset>
                    <fieldset><legend>Departure time</legend><label class="flight-filter-check"><input type="checkbox" name="departure" value="morning"><span>Morning <small>Before 12pm</small></span></label><label class="flight-filter-check"><input type="checkbox" name="departure" value="afternoon"><span>Afternoon <small>12pm–6pm</small></span></label><label class="flight-filter-check"><input type="checkbox" name="departure" value="evening"><span>Evening <small>After 6pm</small></span></label></fieldset>
                    <fieldset><legend>Price</legend><div class="flight-price-range-label"><span>${money(floor, currency)}</span><strong data-price-ceiling>${money(ceiling, currency)}</strong></div><input class="form-range flight-price-range" type="range" min="${floor}" max="${Math.max(floor, ceiling)}" value="${ceiling}" step="100" data-flight-max-price></fieldset>
                    <fieldset><legend>Fare flexibility</legend><label class="flight-filter-check"><input type="checkbox" name="refundable" value="1"><span>Refundable fares</span></label></fieldset>
                </aside>
                <section class="flight-result-content">
                    <div class="flight-results-toolbar"><div><h2>Departing flights</h2><p><span data-visible-result-count>${summaries.length}</span> of ${summaries.length} itineraries · ${offers.length} fare${offers.length === 1 ? '' : 's'}</p></div><label><small>Sort by</small><select data-flight-sort><option value="recommended">Recommended</option><option value="price_asc">Price: low to high</option><option value="price_desc">Price: high to low</option><option value="duration">Shortest duration</option><option value="departure">Earliest departure</option></select></label></div>
                    <div class="admin-flight-result-list" data-admin-flight-result-list></div>
                </section>
                ${isPublicResults ? `<aside class="flight-results-ad" aria-label="Jiro Air charter services"><a href="mailto:${encodeURIComponent(charterContact)}?subject=Jiro%20Air%20charter%20flight%20request"><img src="/images/ads/jiro-air-charter-v1.png" alt="Private jet at sunset" loading="lazy"><span class="jiro-ad-shine"></span><div class="jiro-ad-copy"><small><i></i> Private charter</small><strong>JIRO AIR</strong><h3>Your aircraft.<br>Your schedule.</h3><p>Private, corporate and group charter flights tailored around you.</p><b>Request a charter <i class="bi bi-arrow-up-right"></i></b></div></a></aside>` : ''}
            </div>
            <div class="modal fade flight-details-modal" id="adminFlightDetailsModal" tabindex="-1" aria-labelledby="adminFlightDetailsTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><span class="modal-eyebrow">Complete itinerary</span><h2 class="modal-title" id="adminFlightDetailsTitle">Flight details</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" data-flight-modal-body></div></div></div></div>
            <div class="modal fade flight-revalidation-modal" id="adminFlightRevalidationModal" tabindex="-1" aria-labelledby="adminFlightRevalidationTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><div data-revalidation-progress><span class="revalidation-icon"><i class="bi bi-shield-check"></i></span><h2 id="adminFlightRevalidationTitle">Confirming your flight</h2><p data-revalidation-airline>Confirming availability and the latest fare with the airline…</p><div class="revalidation-progress" aria-hidden="true"><span></span></div><small>This usually takes a few seconds. Please do not close this window.</small><button class="btn btn-link revalidation-cancel" type="button" data-cancel-revalidation>Cancel</button></div><div class="d-none" data-revalidation-result></div></div></div></div></div>
            <div class="offcanvas offcanvas-end flight-fare-panel" tabindex="-1" id="adminFarePanel" aria-labelledby="adminFarePanelTitle"><div class="offcanvas-header"><div><span class="modal-eyebrow">Available options</span><h2 class="offcanvas-title" id="adminFarePanelTitle">Select a fare</h2></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div><div class="offcanvas-body" data-fare-panel-body></div></div>`;
    };

    const renderOffer = (summary, position = 0) => {
        const { offer, segments, first, last, stops, durationMinutes } = summary;
        const airline = offer.validating_airline || first?.marketing_airline || '—';
        const price = money(offer.price.total_minor, offer.price.currency);
        const legs = itineraryLegs(offer);
        const legRows = legs.map((leg, index) => {
            const details = legSummary(leg);
            const connectionAirports = leg.slice(0, -1).map(segment => segment.destination).filter(Boolean);
            return `<div class="flight-compact-leg"><small>${index === 0 ? 'Depart' : index === 1 ? 'Return' : `Leg ${index + 1}`}</small><div class="flight-compact-times"><strong>${time(details.first.departure_at)}</strong><i class="${details.stops ? 'has-stop' : ''}"></i><strong>${time(details.last.arrival_at)}</strong></div><div class="flight-compact-route"><span>${escapeHtml(details.first.origin)} → ${escapeHtml(details.last.destination)}</span><em>${duration(details.elapsed)} · ${stopLabel(details.stops)}${connectionAirports.length ? ` via ${escapeHtml(connectionAirports.join(', '))}` : ''}</em></div></div>`;
        }).join('');
        const baggage = segments.map(segment => segment.checked_baggage_pieces).find(value => value !== null && value !== undefined);
        const cabin = String(first.cabin || criteria.cabin || 'Economy').replaceAll('_', ' ');
        return `<article class="admin-flight-card flight-compact-card flight-result-enter" style="--flight-result-index:${position}" data-offer-id="${escapeHtml(offer.id)}">
            <div class="flight-compact-main"><div class="flight-card-airline">${airlineLogoMarkup(airline)}<span><strong>${escapeHtml(airlineNames[airline] || airline)}</strong><small>${escapeHtml(first.flight_number || '')}${first.operating_airline && first.operating_airline !== airline ? ` · Operated by ${escapeHtml(airlineNames[first.operating_airline] || first.operating_airline)}` : ''}</small></span></div><div class="flight-compact-itinerary">${legRows}</div><div class="flight-card-price"><span class="flight-price-copy"><small>${summary.fares.length > 1 ? 'From' : 'Total fare'}</small><strong>${price}</strong><span>${criteria.trip_type === 'one_way' ? 'One-way' : 'Roundtrip'} per traveller</span></span><button class="btn btn-karossy" type="button" data-select-fare="${summary.index}">Book</button></div></div>
            <div class="flight-compact-footer"><div class="flight-card-benefits"><span><i class="bi bi-luggage"></i>${baggage !== undefined ? `${baggage} checked bag${Number(baggage) === 1 ? '' : 's'}` : 'Baggage not provided'}</span><span><i class="bi bi-person-workspace"></i>${escapeHtml(cabin.charAt(0).toUpperCase() + cabin.slice(1))}</span><span class="${summary.refundable ? 'is-positive' : ''}"><i class="bi bi-${summary.refundable ? 'arrow-counterclockwise' : 'exclamation-circle'}"></i>${summary.refundable ? 'Refundable' : 'Non-refundable'}</span>${first.seats_available !== null && first.seats_available !== undefined ? `<span><i class="bi bi-ticket-perforated"></i>${first.seats_available} seats</span>` : ''}</div><button type="button" data-flight-details="${summary.index}">Flight details <i class="bi bi-chevron-right"></i></button></div>
        </article>`;
    };

    const airportLabel = code => `${escapeHtml(airportNames[code] || code)} <span>(${escapeHtml(code)})</span>`;
    const segmentMinutes = segment => Number(segment.duration_minutes || Math.max(0, Math.round((new Date(segment.arrival_at) - new Date(segment.departure_at)) / 60000)));
    const layoverMinutes = (segments, index) => index < segments.length - 1 ? Math.max(0, Math.round((new Date(segments[index + 1].departure_at) - new Date(segments[index].arrival_at)) / 60000)) : 0;
    const amenityMarkup = segment => {
        const amenities = [
            ['bi-luggage', 'Checked baggage', segment.checked_baggage_pieces !== null && segment.checked_baggage_pieces !== undefined ? `${segment.checked_baggage_pieces} piece${Number(segment.checked_baggage_pieces) === 1 ? '' : 's'} included` : 'Allowance not provided'],
            ['bi-person-workspace', 'Cabin', String(segment.cabin || criteria.cabin || 'Economy').replaceAll('_', ' ')],
            ['bi-ticket-perforated', 'Booking class', segment.booking_code || 'Not provided'],
            ['bi-person-check', 'Seat availability', segment.seats_available !== null && segment.seats_available !== undefined ? `${segment.seats_available} seat${Number(segment.seats_available) === 1 ? '' : 's'} shown` : 'Not provided'],
            ['bi-airplane', 'Aircraft', segment.equipment || 'Not provided'],
        ];
        return `<div class="flight-amenity-grid">${amenities.map(([icon, label, value]) => `<div><i class="bi ${icon}"></i><span><small>${label}</small><strong>${escapeHtml(value)}</strong></span></div>`).join('')}</div><p class="flight-amenity-note"><i class="bi bi-info-circle"></i> Wi-Fi, in-seat power and entertainment details were not supplied for this fare.</p>`;
    };

    const showFlightDetails = summary => {
        const offer = summary.offer;
        const segments = offer.segments;
        const airline = offer.validating_airline || segments[0]?.marketing_airline || '—';
        const modal = results.querySelector('#adminFlightDetailsModal');
        modal.querySelector('#adminFlightDetailsTitle').textContent = `${criteria.origin || segments[0]?.origin || ''} to ${criteria.destination || segments.at(-1)?.destination || ''} flight details`;
        modal.querySelector('[data-flight-modal-body]').innerHTML = `
            <div class="flight-modal-summary">${airlineLogoMarkup(airline)}<div><strong>${escapeHtml(airlineNames[airline] || airline)}</strong><span>${segments.length} flight segment${segments.length === 1 ? '' : 's'} · ${money(offer.price.total_minor, offer.price.currency)}</span></div><span class="${offer.refundable ? 'is-refundable' : ''}">${offer.refundable ? 'Refundable fare available' : 'Non-refundable fare'}</span></div>
            <div class="flight-modal-itinerary">${segments.map((segment, index) => {
                const layover = layoverMinutes(segments, index);
                return `<section class="flight-modal-segment">
                    <div class="flight-modal-segment-heading">${airlineLogoMarkup(segment.marketing_airline || airline, true)}<div><strong>${escapeHtml(airlineNames[segment.marketing_airline] || airlineNames[airline] || segment.marketing_airline || airline)}</strong><span>Flight ${escapeHtml(segment.flight_number)}${segment.operating_airline && segment.operating_airline !== segment.marketing_airline ? ` · Operated by ${escapeHtml(airlineNames[segment.operating_airline] || segment.operating_airline)}` : ''}</span></div></div>
                    <div class="flight-modal-route"><div><time>${time(segment.departure_at)}</time><strong>${airportLabel(segment.origin)}</strong><small>${dateTime(segment.departure_at)}</small></div><div class="flight-modal-duration"><span>${duration(segmentMinutes(segment))}</span><i></i><small>${Number(segment.stops || 0) ? `${segment.stops} technical stop${Number(segment.stops) === 1 ? '' : 's'}` : 'Nonstop'}</small></div><div class="text-end"><time>${time(segment.arrival_at)}</time><strong>${airportLabel(segment.destination)}</strong><small>${dateTime(segment.arrival_at)}</small></div></div>
                    <div class="flight-modal-facts"><div><small>Airline</small><strong>${escapeHtml(airlineNames[segment.marketing_airline] || segment.marketing_airline || airline)}</strong></div><div><small>Flight number</small><strong>${escapeHtml(segment.flight_number)}</strong></div><div><small>Aircraft</small><strong>${escapeHtml(segment.equipment || 'Not provided')}</strong></div><div><small>Cabin</small><strong>${escapeHtml(String(segment.cabin || criteria.cabin || 'Economy').replaceAll('_', ' '))}</strong></div></div>
                    <div class="flight-modal-amenities"><h3>Amenities and fare information</h3>${amenityMarkup(segment)}</div>
                    ${layover ? `<div class="flight-layover"><i class="bi bi-hourglass-split"></i><span><strong>${duration(layover)} layover in ${airportLabel(segment.destination)}</strong><small>Your baggage is normally transferred by the airline; confirm during revalidation.</small></span></div>` : ''}
                </section>`;
            }).join('')}</div>`;
        Modal.getOrCreateInstance(modal).show();
    };

    const fareTitle = offer => {
        const segment = offer.segments[0] || {};
        const cabin = String(segment.cabin || criteria.cabin || 'Economy').replaceAll('_', ' ');
        return `${cabin.charAt(0).toUpperCase()}${cabin.slice(1)}${segment.booking_code ? ` · Class ${segment.booking_code}` : ''}`;
    };

    const showFarePanel = summary => {
        const panel = results.querySelector('#adminFarePanel');
        const airline = summary.offer.validating_airline || summary.first?.marketing_airline || '—';
        panel.querySelector('#adminFarePanelTitle').textContent = `Select a fare to ${summary.last.destination}`;
        panel.querySelector('[data-fare-panel-body]').innerHTML = `<div class="fare-panel-flight"><div>${airlineLogoMarkup(airline, true)}<span><strong>${escapeHtml(airlineNames[airline] || airline)}</strong><small>${time(summary.first.departure_at)}–${time(summary.last.arrival_at)} · ${duration(summary.durationMinutes)} · ${stopLabel(summary.stops)}</small></span></div><p>${summary.fares.length} fare option${summary.fares.length === 1 ? '' : 's'} available for this itinerary.</p></div><div class="fare-option-list">${summary.fares.map((offer, index) => {
            const first = offer.segments[0] || {};
            const bags = first.checked_baggage_pieces;
            return `<article class="fare-option-card"><div class="fare-option-price"><span>${index === 0 && summary.fares.length > 1 ? 'Lowest fare' : 'Available fare'}</span><strong>${money(offer.price.total_minor, offer.price.currency)}</strong><small>${criteria.trip_type === 'one_way' ? 'One-way' : 'Roundtrip'} per traveller</small></div><h3>${escapeHtml(fareTitle(offer))}</h3><ul><li><i class="bi bi-${bags !== null && bags !== undefined ? 'check-circle-fill' : 'info-circle'}"></i>${bags !== null && bags !== undefined ? `${bags} checked bag${Number(bags) === 1 ? '' : 's'} included` : 'Checked baggage allowance not provided'}</li><li><i class="bi bi-${offer.refundable ? 'check-circle-fill' : 'x-circle'}"></i>${offer.refundable ? 'Refundable fare' : 'Non-refundable fare'}</li><li><i class="bi bi-person-check"></i>${first.seats_available !== null && first.seats_available !== undefined ? `${first.seats_available} seats shown in this booking class` : 'Seat availability not provided'}</li></ul><button class="btn btn-karossy w-100" type="button" data-choose-offer="${escapeHtml(offer.id)}">Select this fare</button></article>`;
        }).join('')}</div><p class="fare-panel-disclaimer"><i class="bi bi-shield-check"></i> Fare rules and availability must be revalidated before booking.</p>`;
        Offcanvas.getOrCreateInstance(panel).show();
    };

    let revalidationController = null;
    const revalidateFare = async offer => {
        const panel = results.querySelector('#adminFarePanel');
        const panelInstance = Offcanvas.getOrCreateInstance(panel);

        const modalElement = results.querySelector('#adminFlightRevalidationModal');
        const modal = Modal.getOrCreateInstance(modalElement);
        const progress = modalElement.querySelector('[data-revalidation-progress]');
        const result = modalElement.querySelector('[data-revalidation-result]');
        const airline = offer.validating_airline || offer.segments?.[0]?.marketing_airline || '';
        progress.classList.remove('d-none');
        result.classList.add('d-none');
        result.innerHTML = '';
        modalElement.querySelector('[data-revalidation-airline]').textContent = `Confirming availability and the latest fare with ${airlineNames[airline] || airline || 'the airline'}…`;

        panel.addEventListener('hidden.bs.offcanvas', () => modal.show(), { once: true });
        panelInstance.hide();
        revalidationController = new AbortController();

        try {
            const endpoint = isPublicResults ? revalidateUrlTemplate.replace('__OFFER__', encodeURIComponent(offer.id)) : `/admin/flights/offers/${encodeURIComponent(offer.id)}/revalidate`;
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                signal: revalidationController.signal,
            });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || 'The airline could not confirm this fare.');
            const data = body.data || {};
            if (!data.continue_url) throw new Error('The checkout page could not be opened. Please try again.');
            window.location.assign(data.continue_url);
        } catch (error) {
            if (error.name === 'AbortError') return;
            progress.classList.add('d-none');
            result.classList.remove('d-none');
            result.innerHTML = `<span class="revalidation-icon is-error"><i class="bi bi-exclamation-lg"></i></span><h2>We could not confirm this fare</h2><p>${escapeHtml(error.message)}</p><button class="btn btn-karossy w-100" type="button" data-bs-dismiss="modal">Choose another fare</button>`;
        } finally {
            revalidationController = null;
        }
    };

    const applyFilters = () => {
        const stops = checkedValues('stops');
        const airlines = checkedValues('airlines');
        const periods = checkedValues('departure');
        const refundable = results.querySelector('input[name="refundable"]')?.checked;
        const maxPrice = Number(results.querySelector('[data-flight-max-price]')?.value || Number.MAX_SAFE_INTEGER);
        const sort = results.querySelector('[data-flight-sort]')?.value || 'recommended';
        results.querySelector('[data-price-ceiling]').textContent = money(maxPrice, offers[0]?.price?.currency || criteria.currency);
        let filtered = summaries.filter(item => {
            const period = item.departureHour < 12 ? 'morning' : item.departureHour < 18 ? 'afternoon' : 'evening';
            return (!stops.length || stops.includes(stopGroup(item.stops)))
                && (!airlines.length || airlines.some(code => item.airlines.includes(code)))
                && (!periods.length || periods.includes(period))
                && (!refundable || item.refundable)
                && item.price <= maxPrice;
        });
        filtered.sort((a, b) => {
            if (sort === 'price_asc') return a.price - b.price;
            if (sort === 'price_desc') return b.price - a.price;
            if (sort === 'duration') return a.durationMinutes - b.durationMinutes;
            if (sort === 'departure') return new Date(a.first.departure_at) - new Date(b.first.departure_at);
            return a.price - b.price || a.durationMinutes - b.durationMinutes;
        });
        results.querySelector('[data-visible-result-count]').textContent = filtered.length;
        results.querySelector('[data-admin-flight-result-list]').innerHTML = filtered.map((summary, index) => renderOffer(summary, index)).join('') || '<div class="flight-empty-results"><i class="bi bi-funnel"></i><strong>No flights match these filters</strong><p>Clear one or more filters to see additional offers.</p></div>';
    };

    const bindFilters = () => {
        if (filtersBound) return;
        filtersBound = true;
        results.addEventListener('change', event => {
            if (event.target.matches('input[name], [data-flight-sort]')) applyFilters();
        });
        results.addEventListener('input', event => {
            if (event.target.matches('[data-flight-max-price]')) applyFilters();
        });
        page.addEventListener('click', event => {
            const openMobileFilters = event.target.closest('[data-open-mobile-filters]');
            if (openMobileFilters) {
                results.classList.add('mobile-filters-open');
                document.body.classList.add('mobile-flight-filters-active');
            }
            const closeMobileFilters = event.target.closest('[data-close-mobile-filters]');
            if (closeMobileFilters) {
                results.classList.remove('mobile-filters-open');
                document.body.classList.remove('mobile-flight-filters-active');
            }
            const clear = event.target.closest('[data-clear-flight-filters]');
            if (clear) {
                results.querySelectorAll('.flight-filter-panel input[type="checkbox"]').forEach(input => input.checked = false);
                const range = results.querySelector('[data-flight-max-price]');
                range.value = range.max;
                applyFilters();
            }
            const deal = event.target.closest('[data-deal-airline]');
            if (deal) {
                results.querySelectorAll('input[name="airlines"], input[name="stops"]').forEach(input => { input.checked = false; });
                const airlineInput = results.querySelector(`input[name="airlines"][value="${CSS.escape(deal.dataset.dealAirline)}"]`);
                const stopsInput = results.querySelector(`input[name="stops"][value="${CSS.escape(deal.dataset.dealStops)}"]`);
                if (airlineInput) airlineInput.checked = true;
                if (stopsInput) stopsInput.checked = true;
                applyFilters();
                results.querySelector('.admin-flight-results')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            const details = event.target.closest('[data-flight-details]');
            if (details) {
                const summary = summaries[Number(details.dataset.flightDetails)];
                if (summary) showFlightDetails(summary);
            }
            const selectFare = event.target.closest('[data-select-fare]');
            if (selectFare) {
                const summary = summaries[Number(selectFare.dataset.selectFare)];
                if (summary) showFarePanel(summary);
            }
            const chooseOffer = event.target.closest('[data-choose-offer]');
            if (chooseOffer) {
                const offer = offers.find(item => item.id === chooseOffer.dataset.chooseOffer);
                if (offer) revalidateFare(offer);
            }
            if (event.target.closest('[data-cancel-revalidation]')) {
                revalidationController?.abort();
                Modal.getInstance(results.querySelector('#adminFlightRevalidationModal'))?.hide();
            }
        });
        results.addEventListener('error', event => {
            if (event.target.matches('[data-airline-logo]')) event.target.closest('.airline-logo-wrap')?.classList.add('is-missing');
        }, true);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && results.classList.contains('mobile-filters-open')) {
                results.classList.remove('mobile-filters-open');
                document.body.classList.remove('mobile-flight-filters-active');
            }
        });
    };

    const performSearch = async searchCriteria => {
        const startedAt = Date.now();
        let statusTimer = null;
        message.className = 'flight-search-message d-none';
        results.classList.add('d-none');
        if (submit) {
            submit.disabled = true;
            submit.querySelector('.spinner-border')?.classList.remove('d-none');
        }
        criteria = searchCriteria;

        if (publicSearchModalElement) {
            const status = publicSearchModalElement.querySelector('[data-public-search-status]');
            const messages = [
                `Checking live fares from ${criteria.origin} to ${criteria.destination}…`,
                'Comparing airline schedules and fare families…',
                'Confirming taxes and displaying your total fare…',
                'Preparing your available flight options…',
            ];
            let messageIndex = 0;
            status.textContent = messages[0];
            statusTimer = window.setInterval(() => {
                messageIndex = Math.min(messageIndex + 1, messages.length - 1);
                status.textContent = messages[messageIndex];
            }, 1700);
        }

        try {
            const response = await fetch(searchUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(criteria) });
            const contentType = response.headers.get('content-type') || '';
            const body = contentType.includes('application/json') ? await response.json() : null;
            if (!response.ok || !body) throw new Error('flight-search-unavailable');
            offers = body.data?.offers || [];
            if (!offers.length) {
                summaries = [];
                page.querySelector('[data-flight-results-overview]')?.replaceChildren();
                results.innerHTML = `<div class="flight-no-inventory"><span><i class="bi bi-airplane"></i></span><h2>No live flights were returned</h2><p>We could not find airline availability for this route and these dates. Try nearby dates or update the route to search again.</p><button class="btn btn-karossy" type="button" data-open-flight-search><i class="bi bi-calendar3"></i> Change search</button></div>`;
                results.classList.remove('d-none');
                results.querySelector('[data-open-flight-search]')?.addEventListener('click', () => {
                    document.querySelector('[data-bs-target="#inlineFlightSearch"]')?.click();
                    document.querySelector('#inlineFlightSearch')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                return;
            }
            summaries = groupItineraries(offers.map(summarize).filter(item => item.first && item.last));
            const overview = page.querySelector('[data-flight-results-overview]');
            if (overview) overview.innerHTML = dealsOverview(offers[0]?.price?.currency || criteria.currency || 'NGN');
            filterShell();
            bindFilters();
            applyFilters();
            results.classList.remove('d-none');
            if (isPublicResults) {
                const headingCopy = page.querySelector('.public-results-heading p');
                if (headingCopy) headingCopy.textContent = `${summaries.length} live ${summaries.length === 1 ? 'itinerary' : 'itineraries'} · ${offers.length} ${offers.length === 1 ? 'fare' : 'fares'} · Taxes and fees included`;
            }
            (page.querySelector('.public-flight-results-header') || results).scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            message.innerHTML = `<div><strong>Temporary network issue</strong><span>We could not connect to the airline network. Please check your connection and try again shortly.</span></div><button type="button" class="btn btn-sm btn-outline-danger" data-retry-flight-search><i class="bi bi-arrow-clockwise"></i> Try again</button>`;
            message.className = 'flight-search-message alert alert-danger mt-3';
            message.querySelector('[data-retry-flight-search]')?.addEventListener('click', () => {
                if (publicSearchModalElement) Modal.getOrCreateInstance(publicSearchModalElement).show();
                performSearch(criteria);
            }, { once: true });
        } finally {
            if (statusTimer) window.clearInterval(statusTimer);
            await closePublicSearchModal(startedAt);
            if (submit) {
                submit.disabled = false;
                submit.querySelector('.spinner-border')?.classList.add('d-none');
            }
        }
    };

    if (form) {
        form.elements.session_id.value = sessionId();
        form.addEventListener('submit', event => {
            event.preventDefault();
            const submittedCriteria = Object.fromEntries(new FormData(form).entries());
            ['adults', 'children', 'infants'].forEach(key => submittedCriteria[key] = Number(submittedCriteria[key]));
            if (submittedCriteria.trip_type === 'one_way') delete submittedCriteria.return_date;
            if (submittedCriteria.trip_type === 'multi_city') {
                submittedCriteria.segments = [...searchRoot.querySelectorAll('[data-flight-segment]')].map(segment => ({ origin: segment.querySelector('[data-segment-origin-code]').value, destination: segment.querySelector('[data-segment-destination-code]').value, departure_date: segment.querySelector('[data-flight-segment-date]').value }));
                submittedCriteria.origin = submittedCriteria.segments[0]?.origin;
                submittedCriteria.destination = submittedCriteria.segments.at(-1)?.destination;
                submittedCriteria.departure_date = submittedCriteria.segments[0]?.departure_date;
                delete submittedCriteria.return_date;
            }
            if (publicSearchModalElement) Modal.getOrCreateInstance(publicSearchModalElement).show();
            performSearch(submittedCriteria);
            if (isPublicResults) {
                const query = new URLSearchParams(Object.entries(submittedCriteria).filter(([, value]) => value !== undefined && value !== '')).toString();
                window.history.replaceState({ flightSearch: true }, '', `${window.location.pathname}?${query}`);
            }
        });
    }
    if (isPublicResults) {
        criteria = JSON.parse(page.querySelector('[data-flight-search-criteria]')?.textContent || '{}');
        publicSearchModalElement.addEventListener('shown.bs.modal', () => performSearch(criteria), { once: true });
        window.requestAnimationFrame(() => window.requestAnimationFrame(() => publicSearchModal.show()));
    }
}
