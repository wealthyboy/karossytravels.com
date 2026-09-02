import './bootstrap';
import * as bootstrap from 'bootstrap';
import flatpickr from 'flatpickr';
import { searchAirports } from './airports';
import './admin-flight-search';

// Blade page scripts use Bootstrap's programmatic modal API after the Vite
// bundle has loaded. Expose the imported module explicitly instead of relying
// on a global created as a side effect.
window.bootstrap = bootstrap;

const initialiseAirportAutocomplete = (input) => {
    if (input.dataset.airportAutocompleteReady) return;
    input.dataset.airportAutocompleteReady = 'true';

    const control = input.closest('.flight-search-control');
    if (!control) return;
    control.classList.add('airport-autocomplete-control');
    const codeInput = control.querySelector('[data-airport-code-input], [data-segment-airport-code]');

    const results = document.createElement('div');
    results.className = 'airport-autocomplete-results d-none';
    results.setAttribute('role', 'listbox');
    control.append(results);
    let activeIndex = -1;

    const close = () => {
        results.classList.add('d-none');
        results.replaceChildren();
        activeIndex = -1;
        input.setAttribute('aria-expanded', 'false');
    };

    const choose = (airport) => {
        const label = `${airport.city}, ${airport.country} (${airport.code}–${airport.name})`;
        input.value = label;
        input.title = label;
        input.dataset.airportCode = airport.code;
        input.dataset.airportLabel = label;
        if (codeInput) codeInput.value = airport.code;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        input.blur();
    };

    const highlight = (index) => {
        const options = [...results.querySelectorAll('[role="option"]')];
        if (!options.length) return;
        activeIndex = (index + options.length) % options.length;
        options.forEach((option, optionIndex) => option.classList.toggle('active', optionIndex === activeIndex));
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    const render = () => {
        const query = input.value.trim();
        input.dataset.airportCode = '';
        input.removeAttribute('title');
        if (codeInput) codeInput.value = '';
        const matches = searchAirports(query);
        results.replaceChildren();
        activeIndex = -1;
        if (!query || !matches.length) return close();

        matches.forEach((airport) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'airport-autocomplete-option';
            option.setAttribute('role', 'option');
            option.innerHTML = `<i class="bi bi-airplane"></i><span><strong>${airport.city} <b>${airport.code}</b></strong><small>${airport.name} · ${airport.country}</small></span>`;
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => choose(airport));
            results.append(option);
        });
        results.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
    };

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.addEventListener('input', render);
    input.addEventListener('focus', () => { if (input.value.length >= 1) render(); });
    input.addEventListener('keydown', (event) => {
        if (results.classList.contains('d-none')) return;
        if (event.key === 'ArrowDown') { event.preventDefault(); highlight(activeIndex + 1); }
        if (event.key === 'ArrowUp') { event.preventDefault(); highlight(activeIndex - 1); }
        if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            const airport = searchAirports(input.value)[activeIndex];
            if (airport) choose(airport);
        }
        if (event.key === 'Escape') close();
    });
    input.addEventListener('blur', () => window.setTimeout(close, 120));
};

document.querySelectorAll('[data-airport-autocomplete]').forEach(initialiseAirportAutocomplete);

document.querySelectorAll('[data-hotel-search]').forEach((hotelSearch) => {
    const destination = hotelSearch.querySelector('[data-hotel-destination]');
    const destinationCode = hotelSearch.querySelector('[data-hotel-destination-code]');
    const sessionId = hotelSearch.querySelector('[name="session_id"]');
    if (sessionId && !sessionId.value) sessionId.value = crypto.randomUUID?.() || `00000000-0000-4000-8000-${Date.now().toString().padStart(12, '0').slice(-12)}`;
    const destinationControl = destination.closest('.hotel-destination-control');
    const destinationResults = document.createElement('div');
    destinationResults.className = 'airport-autocomplete-results d-none';
    destinationControl.append(destinationResults);

    const closeDestinations = () => destinationResults.classList.add('d-none');
    destination.addEventListener('input', () => {
        destinationCode.value = '';
        const cities = [...new Map(searchAirports(destination.value, 12).map((airport) => [`${airport.city}|${airport.country}`, airport])).values()].slice(0, 6);
        destinationResults.replaceChildren();
        if (!destination.value.trim() || !cities.length) return closeDestinations();
        cities.forEach((airport) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'airport-autocomplete-option';
            option.innerHTML = `<i class="bi bi-geo-alt"></i><span><strong>${airport.city}, ${airport.country}</strong><small>Hotels and stays near ${airport.code}</small></span>`;
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => {
                destination.value = `${airport.city}, ${airport.country}`;
                destination.title = destination.value;
                destinationCode.value = airport.code;
                destinationControl.classList.remove('is-invalid');
                closeDestinations();
                destination.blur();
            });
            destinationResults.append(option);
        });
        destinationResults.classList.remove('d-none');
    });
    destination.addEventListener('blur', () => window.setTimeout(closeDestinations, 120));

    const dateDisplay = hotelSearch.querySelector('[data-hotel-date-range]');
    const checkIn = hotelSearch.querySelector('[data-hotel-check-in]');
    const checkOut = hotelSearch.querySelector('[data-hotel-check-out]');
    flatpickr(dateDisplay, {
        mode: 'range',
        minDate: 'today',
        dateFormat: 'Y-m-d',
        altInput: true,
        altInputClass: 'hotel-date-range-input',
        altFormat: 'D, M j, Y',
        defaultDate: [checkIn.value, checkOut.value].filter(Boolean),
        disableMobile: true,
        showMonths: window.matchMedia('(min-width: 992px)').matches ? 2 : 1,
        monthSelectorType: 'static',
        position: 'below right',
        conjunction: ' — ',
        onReady: (_, __, instance) => instance.calendarContainer.classList.add('karossy-flight-calendar'),
        onChange: (dates) => {
            checkIn.value = dates[0] ? formatLocalDate(dates[0]) : '';
            checkOut.value = dates[1] ? formatLocalDate(dates[1]) : '';
        },
    });

    const guests = hotelSearch.querySelector('[data-hotel-guests]');
    const guestsToggle = guests.querySelector('[data-hotel-guests-toggle]');
    const guestsPopover = guests.querySelector('[data-hotel-guests-popover]');
    const guestsSummary = guests.querySelector('[data-hotel-guests-summary]');
    const guestRows = [...guests.querySelectorAll('[data-hotel-counter]')];
    const valueFor = (row) => Number(row.querySelector('[data-counter-value]').textContent);
    const updateGuests = () => {
        const values = Object.fromEntries(guestRows.map((row) => [row.dataset.field, valueFor(row)]));
        guestRows.forEach((row) => {
            const value = valueFor(row);
            row.querySelector('[data-counter-input]').value = value;
            row.querySelector('[data-counter-minus]').disabled = value <= Number(row.dataset.min);
            row.querySelector('[data-counter-plus]').disabled = value >= Number(row.dataset.max);
        });
        const travellers = values.adults + values.children;
        guestsSummary.textContent = `${travellers} ${travellers === 1 ? 'traveller' : 'travellers'}, ${values.rooms} ${values.rooms === 1 ? 'room' : 'rooms'}`;
    };
    const closeGuests = () => { guestsPopover.classList.add('d-none'); guestsToggle.setAttribute('aria-expanded', 'false'); };
    guestsToggle.addEventListener('click', () => {
        const opening = guestsPopover.classList.contains('d-none');
        guestsPopover.classList.toggle('d-none', !opening);
        guestsToggle.setAttribute('aria-expanded', String(opening));
    });
    guestRows.forEach((row) => {
        row.querySelector('[data-counter-minus]').addEventListener('click', () => { row.querySelector('[data-counter-value]').textContent = Math.max(Number(row.dataset.min), valueFor(row) - 1); updateGuests(); });
        row.querySelector('[data-counter-plus]').addEventListener('click', () => { row.querySelector('[data-counter-value]').textContent = Math.min(Number(row.dataset.max), valueFor(row) + 1); updateGuests(); });
    });
    guests.querySelector('[data-hotel-guests-done]').addEventListener('click', closeGuests);
    document.addEventListener('click', (event) => { if (!guests.contains(event.target)) closeGuests(); });
    updateGuests();
    hotelSearch.addEventListener('submit', (event) => {
        if (!destinationCode.value || !checkIn.value || !checkOut.value) {
            event.preventDefault();
            destination.focus();
            destinationControl.classList.add('is-invalid');
            return;
        }
        const submit = hotelSearch.querySelector('.hotel-search-submit');
        submit.disabled = true;
        submit.querySelector('.spinner-border')?.classList.remove('d-none');
    });
});

const activatePublicService = (service) => {
    document.querySelectorAll('[data-public-service-tab]').forEach((tab) => {
        const active = tab.dataset.publicServiceTab === service;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', String(active));
    });
    document.querySelectorAll('[data-public-service-panel]').forEach((panel) => panel.classList.toggle('d-none', panel.dataset.publicServicePanel !== service));
    document.querySelectorAll('[data-header-service]').forEach((link) => link.classList.toggle('active', link.dataset.headerService === service));
};

document.querySelectorAll('[data-public-service-tab]').forEach((tab) => tab.addEventListener('click', () => activatePublicService(tab.dataset.publicServiceTab)));
document.querySelectorAll('[data-header-service]').forEach((link) => link.addEventListener('click', () => activatePublicService(link.dataset.headerService)));
document.querySelectorAll('[data-toggle-password]').forEach((button) => button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.togglePassword);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    button.querySelector('i')?.classList.toggle('bi-eye');
    button.querySelector('i')?.classList.toggle('bi-eye-slash');
}));
document.querySelectorAll('[data-loading-form]').forEach((form) => form.addEventListener('submit', () => {
    const button = form.querySelector('[data-submit-loading]');
    if (!button) return;
    button.disabled = true;
    button.classList.add('is-loading');
    button.querySelector('[data-submit-spinner]')?.classList.remove('d-none');
    const label = button.querySelector('[data-submit-label]');
    if (label) label.textContent = label.textContent === 'Sign in' ? 'Signing in…' : 'Saving…';
}));
document.querySelectorAll('.fare-choice input').forEach((input) => input.addEventListener('change', () => {
    document.querySelectorAll('.fare-choice').forEach((choice) => choice.classList.toggle('selected', choice.contains(input)));
}));
document.querySelectorAll('[data-payment-tab]').forEach((tab) => tab.addEventListener('click', () => {
    document.querySelectorAll('[data-payment-tab]').forEach((button) => button.classList.toggle('active', button === tab));
    document.querySelectorAll('[data-payment-panel]').forEach((panel) => panel.classList.toggle('d-none', panel.dataset.paymentPanel !== tab.dataset.paymentTab));
}));
document.querySelectorAll('[data-traveller-picker]').forEach((picker) => {
    const toggle = picker.querySelector('[data-traveller-toggle]');
    const popover = picker.querySelector('[data-traveller-popover]');
    const summary = picker.querySelector('[data-traveller-summary]');
    const cabin = picker.querySelector('[data-cabin-select]');
    const rows = [...picker.querySelectorAll('[data-traveller-counter]')];

    const rowValue = (row) => Number(row.querySelector('[data-counter-value]').textContent);
    const update = () => {
        const values = Object.fromEntries(rows.map((row) => [row.dataset.field, rowValue(row)]));
        rows.forEach((row) => {
            const value = rowValue(row);
            row.querySelector('[data-counter-minus]').disabled = value <= Number(row.dataset.min);
            row.querySelector('[data-counter-plus]').disabled = value >= Number(row.dataset.max);
            const input = row.querySelector('[data-counter-input]');
            input.value = row.dataset.field === 'lap_infants' ? values.lap_infants + values.seat_infants : value;
        });
        const total = values.adults + values.children + values.lap_infants + values.seat_infants;
        summary.textContent = `${total} ${total === 1 ? 'traveller' : 'travellers'}, ${cabin.options[cabin.selectedIndex].text}`;
    };
    const close = () => { popover.classList.add('d-none'); toggle.setAttribute('aria-expanded', 'false'); };

    toggle.addEventListener('click', () => {
        const opening = popover.classList.contains('d-none');
        document.querySelectorAll('[data-traveller-popover]').forEach((other) => other.classList.add('d-none'));
        popover.classList.toggle('d-none', !opening);
        toggle.setAttribute('aria-expanded', String(opening));
    });
    rows.forEach((row) => {
        row.querySelector('[data-counter-minus]').addEventListener('click', () => {
            const value = Math.max(Number(row.dataset.min), rowValue(row) - 1);
            row.querySelector('[data-counter-value]').textContent = value;
            update();
        });
        row.querySelector('[data-counter-plus]').addEventListener('click', () => {
            const value = Math.min(Number(row.dataset.max), rowValue(row) + 1);
            row.querySelector('[data-counter-value]').textContent = value;
            update();
        });
    });
    cabin.addEventListener('change', update);
    picker.querySelector('[data-traveller-done]').addEventListener('click', close);
    document.addEventListener('click', (event) => { if (!picker.contains(event.target)) close(); });
    update();
});

const formatLocalDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const formatFare = (fare) => {
    if (fare === null || fare === undefined) return null;
    if (typeof fare === 'string') return fare;

    const amount = typeof fare === 'number' ? fare : (fare.amount ?? fare.total ?? fare.price);
    if (amount === null || amount === undefined) return null;

    const currency = typeof fare === 'object' ? (fare.currency || 'NGN') : 'NGN';
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
};

document.querySelectorAll('[data-flight-search]').forEach((searchRoot) => {
    const displayInput = searchRoot.querySelector('[data-flight-date-range]');
    const departureInput = searchRoot.querySelector('[data-departure-date]');
    const returnInput = searchRoot.querySelector('[data-return-date]');
    const dateLabel = searchRoot.querySelector('[data-flight-date-label]');
    const tripInputs = [...searchRoot.querySelectorAll('.trip-type-input')];
    const form = searchRoot.querySelector('.flight-search-form');
    const sessionId = form.querySelector('[name="session_id"]');
    if (sessionId && !sessionId.value) sessionId.value = crypto.randomUUID?.() || `00000000-0000-4000-8000-${Date.now().toString().padStart(12, '0').slice(-12)}`;
    const tripTypeValue = form.querySelector('[data-trip-type-value]');
    const standardFields = form.querySelector('[data-standard-flight-fields]');
    const multiCityFields = form.querySelector('[data-multi-city-fields]');
    if (!displayInput || !departureInput || !returnInput) return;

    const standardPair = searchRoot.querySelector('[data-standard-flight-fields] .flight-location-pair');
    const standardSwap = standardPair?.querySelector('.flight-swap-button');
    standardSwap?.addEventListener('click', () => {
        const fields = [...standardPair.querySelectorAll('[data-airport-autocomplete]')];
        const codes = [...standardPair.querySelectorAll('[data-airport-code-input]')];
        if (fields.length !== 2) return;
        [fields[0].value, fields[1].value] = [fields[1].value, fields[0].value];
        [fields[0].title, fields[1].title] = [fields[1].title, fields[0].title];
        [fields[0].dataset.airportCode, fields[1].dataset.airportCode] = [fields[1].dataset.airportCode || '', fields[0].dataset.airportCode || ''];
        if (codes.length === 2) [codes[0].value, codes[1].value] = [codes[1].value, codes[0].value];
    });

    let calendar;
    let fares = window.karossyFlightDatePrices || {};

    const fareAmounts = () => Object.values(fares)
        .map((fare) => typeof fare === 'number' ? fare : (fare?.amount ?? fare?.total ?? fare?.price))
        .filter((amount) => Number.isFinite(Number(amount)))
        .map(Number);

    const buildCalendar = (tripType) => {
        const isOneWay = tripType === 'one_way';
        const selectedDates = [departureInput.value, ...(!isOneWay && returnInput.value ? [returnInput.value] : [])].filter(Boolean);
        calendar?.destroy();
        dateLabel.textContent = isOneWay ? 'Departure' : 'Departure — Return';

        calendar = flatpickr(displayInput, {
            mode: isOneWay ? 'single' : 'range',
            minDate: 'today',
            dateFormat: 'Y-m-d',
            altInput: true,
            altInputClass: 'flight-date-range-input',
            altFormat: 'D, M j, Y',
            defaultDate: selectedDates,
            disableMobile: true,
            showMonths: window.matchMedia('(min-width: 992px)').matches ? 2 : 1,
            monthSelectorType: 'static',
            position: 'below right',
            conjunction: ' — ',
            onReady: (_, __, instance) => instance.calendarContainer.classList.add('karossy-flight-calendar'),
            onChange: (dates) => {
                departureInput.value = dates[0] ? formatLocalDate(dates[0]) : '';
                returnInput.value = !isOneWay && dates[1] ? formatLocalDate(dates[1]) : '';
            },
            onDayCreate: (_, __, ___, dayElement) => {
                const fare = fares[formatLocalDate(dayElement.dateObj)];
                const formattedFare = formatFare(fare);
                if (!formattedFare) return;

                const price = document.createElement('span');
                price.className = 'flatpickr-day-price';
                price.textContent = formattedFare;
                dayElement.append(price);

                const amounts = fareAmounts();
                const numericFare = Number(typeof fare === 'number' ? fare : (fare?.amount ?? fare?.total ?? fare?.price));
                if (amounts.length && numericFare === Math.min(...amounts)) dayElement.classList.add('has-lowest-fare');
                if (amounts.length > 2 && numericFare === Math.max(...amounts)) dayElement.classList.add('has-high-fare');
            },
        });
    };

    buildCalendar(tripInputs.find((input) => input.checked)?.value || 'round_trip');
    tripInputs.forEach((input) => input.addEventListener('change', () => buildCalendar(input.value)));

    searchRoot.addEventListener('karossy:flight-date-prices', (event) => {
        fares = event.detail?.prices || {};
        calendar?.redraw();
    });

    const segmentContainer = searchRoot.querySelector('[data-multi-city-segments]');
    const segmentTemplate = document.querySelector('#multi-city-segment-template');
    const addSegmentButton = searchRoot.querySelector('[data-add-flight-segment]');

    const renumberSegments = () => {
        const segments = [...segmentContainer.querySelectorAll('[data-flight-segment]')];
        segments.forEach((segment, index) => {
            segment.querySelector('[data-segment-number]').textContent = index + 1;
            segment.querySelector('[data-remove-flight-segment]').classList.toggle('invisible', segments.length <= 2);
            segment.querySelector('[data-segment-origin-code]').name = `segments[${index}][origin]`;
            segment.querySelector('[data-segment-destination-code]').name = `segments[${index}][destination]`;
            segment.querySelector('[data-flight-segment-date]').name = `segments[${index}][departure_date]`;
        });
        addSegmentButton.disabled = segments.length >= 6;
    };

    const addSegment = ({ origin = '', originCode = '', destination = '', destinationCode = '', date = '' } = {}) => {
        const segment = segmentTemplate.content.firstElementChild.cloneNode(true);
        const originInput = segment.querySelector('[data-segment-origin]');
        const destinationInput = segment.querySelector('[data-segment-destination]');
        const originCodeInput = segment.querySelector('[data-segment-origin-code]');
        const destinationCodeInput = segment.querySelector('[data-segment-destination-code]');
        const dateInput = segment.querySelector('[data-flight-segment-date]');
        originInput.value = origin;
        destinationInput.value = destination;
        originCodeInput.value = originCode;
        destinationCodeInput.value = destinationCode;
        const multiCityActive = tripInputs.find((input) => input.checked)?.value === 'multi_city';
        [originInput, destinationInput, dateInput].forEach((input) => input.disabled = !multiCityActive);

        [originInput, destinationInput].forEach(initialiseAirportAutocomplete);
        segment.querySelector('[data-segment-swap]').addEventListener('click', () => {
            [originInput.value, destinationInput.value] = [destinationInput.value, originInput.value];
            [originCodeInput.value, destinationCodeInput.value] = [destinationCodeInput.value, originCodeInput.value];
        });
        segment.querySelector('[data-remove-flight-segment]').addEventListener('click', () => {
            if (segmentContainer.querySelectorAll('[data-flight-segment]').length <= 2) return;
            segment.remove();
            renumberSegments();
        });

        segmentContainer.append(segment);
        flatpickr(dateInput, {
            minDate: 'today',
            dateFormat: 'Y-m-d',
            altInput: true,
            altInputClass: 'flight-date-range-input',
            altFormat: 'D, M j, Y',
            defaultDate: date || undefined,
            disableMobile: true,
            monthSelectorType: 'static',
            position: 'below right',
            onReady: (_, __, instance) => instance.calendarContainer.classList.add('karossy-flight-calendar'),
        });
        renumberSegments();
    };

    addSegment({ date: departureInput.value });
    addSegment({ date: returnInput.value });
    addSegmentButton.addEventListener('click', () => {
        const lastSegment = segmentContainer.querySelector('[data-flight-segment]:last-child');
        addSegment({
            origin: lastSegment?.querySelector('[data-segment-destination]').value || '',
            originCode: lastSegment?.querySelector('[data-segment-destination-code]').value || '',
        });
    });

    const setTripType = (tripType) => {
        const multiCityActive = tripType === 'multi_city';
        tripTypeValue.value = tripType;
        standardFields.classList.toggle('d-none', multiCityActive);
        multiCityFields.classList.toggle('d-none', !multiCityActive);
        standardFields.querySelectorAll('input, select, button').forEach((field) => field.disabled = multiCityActive);
        segmentContainer.querySelectorAll('input, select, button').forEach((field) => field.disabled = !multiCityActive);
        addSegmentButton.disabled = !multiCityActive || segmentContainer.querySelectorAll('[data-flight-segment]').length >= 6;
    };

    tripInputs.forEach((input) => input.addEventListener('change', () => setTripType(input.value)));
    setTripType(tripInputs.find((input) => input.checked)?.value || 'round_trip');
});

document.querySelectorAll('.admin-table-card').forEach((tableCard) => {
    const selectAll = tableCard.querySelector('.select-all-rows');
    const checkboxes = [...tableCard.querySelectorAll('.row-checkbox:not(:disabled)')];
    const bulkButton = tableCard.querySelector('.bulk-delete-button');
    const count = tableCard.querySelector('.selected-count');

    const updateSelection = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (selectAll) {
            selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
            selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
            selectAll.disabled = checkboxes.length === 0;
        }
        if (bulkButton) bulkButton.disabled = selected === 0;
        if (count) count.textContent = selected > 0 ? `(${selected})` : '';
    };

    selectAll?.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => checkbox.checked = selectAll.checked);
        updateSelection();
    });
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSelection));
    updateSelection();
});

document.querySelectorAll('[data-currency-rate-search]').forEach((input) => {
    input.addEventListener('input', () => {
        const query = input.value.trim().toUpperCase();
        document.querySelectorAll('[data-currency-rate-row]').forEach((row) => {
            row.classList.toggle('d-none', query !== '' && !row.dataset.code.includes(query));
        });
    });
});

document.querySelectorAll('[data-currency-rate-row]').forEach((row) => {
    const direction = row.querySelector('[data-rate-direction]');
    const mode = row.querySelector('[data-rate-mode]');
    const value = row.querySelector('[data-rate-value]');
    const unit = row.querySelector('[data-rate-unit]');
    const customerRate = row.querySelector('[data-customer-rate]');
    if (!direction || !mode || !value || !customerRate) return;

    const calculate = () => {
        const liveRate = Number(row.dataset.liveRate || 0);
        const amount = Math.max(0, Number(value.value || 0));
        const adjustment = mode.value === 'fixed' ? amount : liveRate * (amount / 100);
        let effective = liveRate;
        if (direction.value === 'markup') effective += adjustment;
        if (direction.value === 'markdown') effective = Math.max(0, effective - adjustment);
        customerRate.textContent = effective.toLocaleString(undefined, { minimumFractionDigits: 6, maximumFractionDigits: 6 });
        unit.textContent = mode.value === 'fixed' ? row.dataset.code.split(' ')[0] : '%';
    };

    [direction, mode, value].forEach((field) => field.addEventListener('input', calculate));
    calculate();
});
