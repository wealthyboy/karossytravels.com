import './bootstrap';
import * as bootstrap from 'bootstrap';
import flatpickr from 'flatpickr';
import { searchAirports } from './airports';
import './admin-flight-search';

document.addEventListener('click', event => {
    const openButton = event.target.closest('[data-open-public-filters]');
    const closeButton = event.target.closest('[data-close-public-filters]');
    const control = openButton || closeButton;

    if (!control) return;

    const shell = control.closest('[data-public-filter-shell]');
    if (!shell) return;

    const shouldOpen = Boolean(openButton);
    shell.classList.toggle('mobile-filters-open', shouldOpen);
    document.body.classList.toggle('mobile-public-filters-active', shouldOpen);
});

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;

    const openShell = document.querySelector('[data-public-filter-shell].mobile-filters-open');
    if (!openShell) return;

    openShell.classList.remove('mobile-filters-open');
    document.body.classList.remove('mobile-public-filters-active');
});
import './hotel-results';
import './passport-scanner';

// Blade page scripts use Bootstrap's programmatic modal API after the Vite
// bundle has loaded. Expose the imported module explicitly instead of relying
// on a global created as a side effect.
window.bootstrap = bootstrap;

document.querySelectorAll('[data-checkout-date-of-birth]').forEach(input => {
    const latestEligibleDate = input.dataset.maxDate || null;
    const picker = flatpickr(input, {
        allowInput: true,
        altFormat: 'd/m/Y',
        altInput: true,
        altInputClass: input.className,
        dateFormat: 'Y-m-d',
        defaultDate: input.value || null,
        disableMobile: true,
        maxDate: latestEligibleDate,
        monthSelectorType: 'dropdown',
        onOpen: (_, __, instance) => {
            if (!instance.selectedDates.length && latestEligibleDate) instance.jumpToDate(latestEligibleDate);
        },
        onReady: (_, __, instance) => {
            instance.calendarContainer.classList.add('karossy-flight-calendar', 'karossy-checkout-calendar');
            instance.altInput?.setAttribute('autocomplete', 'bday');
            instance.altInput?.setAttribute('aria-label', 'Date of birth');
        },
    });

    // Keep a blank DOB blank. The cutoff is only the calendar's initial view,
    // never an assumed birth date that could be submitted accidentally.
    if (!input.value) picker.clear(false);
});

document.querySelectorAll('[data-checkout-passport-expiry]').forEach(input => {
    flatpickr(input, {
        allowInput: true,
        altFormat: 'd/m/Y',
        altInput: true,
        altInputClass: input.className,
        dateFormat: 'Y-m-d',
        defaultDate: input.value || null,
        disableMobile: true,
        minDate: new Date().fp_incr(1),
        monthSelectorType: 'dropdown',
        onReady: (_, __, instance) => instance.calendarContainer.classList.add('karossy-flight-calendar', 'karossy-checkout-calendar'),
    });
});

document.querySelectorAll('[data-global-route-card]').forEach((card) => {
    const routes = [
        ['LHR', 'London', 'DXB', 'Dubai'],
        ['JFK', 'New York', 'CDG', 'Paris'],
        ['SIN', 'Singapore', 'HND', 'Tokyo'],
        ['SYD', 'Sydney', 'AKL', 'Auckland'],
        ['CPT', 'Cape Town', 'DOH', 'Doha'],
        ['LOS', 'Lagos', 'YYZ', 'Toronto'],
    ];
    const fields = {
        originCode: card.querySelector('[data-route-origin-code]'),
        originCity: card.querySelector('[data-route-origin-city]'),
        destinationCode: card.querySelector('[data-route-destination-code]'),
        destinationCity: card.querySelector('[data-route-destination-city]'),
    };
    let routeIndex = Math.floor(Date.now() / 6000) % routes.length;

    const renderRoute = () => {
        const [originCode, originCity, destinationCode, destinationCity] = routes[routeIndex];
        fields.originCode.textContent = originCode;
        fields.originCity.textContent = originCity;
        fields.destinationCode.textContent = destinationCode;
        fields.destinationCity.textContent = destinationCity;
    };

    renderRoute();
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    window.setInterval(() => {
        card.classList.add('is-changing');
        window.setTimeout(() => {
            routeIndex = (routeIndex + 1) % routes.length;
            renderRoute();
            card.classList.remove('is-changing');
        }, 220);
    }, 6000);
});

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

const initialiseSearchableSelect = (select) => {
    if (select.dataset.searchableSelectReady) return;
    select.dataset.searchableSelectReady = 'true';

    const label = select.closest('label');
    if (!label) return;

    const options = [...select.options]
        .filter((option) => option.value !== '')
        .map((option) => ({ value: option.value, label: option.textContent.trim() }));
    const placeholder = select.dataset.searchPlaceholder || select.options[0]?.textContent?.trim() || 'Search';
    const wasRequired = select.required;

    const control = document.createElement('div');
    control.className = 'searchable-select';

    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'searchable-select-input';
    input.placeholder = placeholder;
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    if (wasRequired) input.required = true;

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'searchable-select-toggle';
    toggle.setAttribute('aria-label', 'Show options');
    toggle.innerHTML = '<i class="bi bi-chevron-down"></i>';

    const results = document.createElement('div');
    results.className = 'searchable-select-results d-none';
    results.setAttribute('role', 'listbox');

    select.parentNode.insertBefore(control, select);
    control.append(select, input, toggle, results);
    select.classList.add('searchable-select-source');
    select.required = false;

    if (select.value) {
        input.value = select.options[select.selectedIndex]?.textContent?.trim() || select.value;
    }

    let activeIndex = -1;
    let visibleOptions = [];

    const close = () => {
        results.classList.add('d-none');
        results.replaceChildren();
        activeIndex = -1;
        visibleOptions = [];
        input.setAttribute('aria-expanded', 'false');
        control.classList.remove('is-open');
    };

    const choose = (option) => {
        select.value = option.value;
        input.value = option.label;
        input.setCustomValidity('');
        label.classList.remove('is-invalid');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    };

    const highlight = (index) => {
        const resultOptions = [...results.querySelectorAll('[role="option"]')];
        if (!resultOptions.length) return;
        activeIndex = (index + resultOptions.length) % resultOptions.length;
        resultOptions.forEach((option, optionIndex) => {
            const active = optionIndex === activeIndex;
            option.classList.toggle('active', active);
            option.setAttribute('aria-selected', String(active));
        });
        resultOptions[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    const render = (query = input.value) => {
        const normalized = query.trim().toLowerCase();
        visibleOptions = options.filter((option) => !normalized || option.label.toLowerCase().includes(normalized));
        results.replaceChildren();
        activeIndex = -1;

        if (!visibleOptions.length) {
            const empty = document.createElement('div');
            empty.className = 'searchable-select-empty';
            empty.textContent = 'No matching option';
            results.append(empty);
        } else {
            visibleOptions.forEach((option) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'searchable-select-option';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                const icon = document.createElement('i');
                icon.className = 'bi bi-geo-alt';
                const text = document.createElement('span');
                text.textContent = option.label;
                button.append(icon, text);
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => choose(option));
                results.append(button);
            });
        }

        results.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
        control.classList.add('is-open');
    };

    const syncTypedValue = () => {
        const typed = input.value.trim().toLowerCase();
        const exact = options.find((option) => option.label.toLowerCase() === typed);
        select.value = exact?.value || '';
        input.setCustomValidity(wasRequired && !exact ? 'Choose an option from the list.' : '');
        label.classList.toggle('is-invalid', wasRequired && input.value !== '' && !exact);
        render(input.value);
    };

    input.addEventListener('input', syncTypedValue);
    input.addEventListener('focus', () => render(input.value));
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (results.classList.contains('d-none')) render(input.value);
            highlight(activeIndex + 1);
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (results.classList.contains('d-none')) render(input.value);
            highlight(activeIndex - 1);
        }
        if (event.key === 'Enter' && activeIndex >= 0 && visibleOptions[activeIndex]) {
            event.preventDefault();
            choose(visibleOptions[activeIndex]);
        }
        if (event.key === 'Escape') close();
    });
    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            const exact = options.find((option) => option.label.toLowerCase() === input.value.trim().toLowerCase());
            if (exact) {
                select.value = exact.value;
                input.value = exact.label;
                input.setCustomValidity('');
                label.classList.remove('is-invalid');
            } else if (!input.value.trim()) {
                select.value = '';
                input.setCustomValidity(wasRequired ? 'Choose an option from the list.' : '');
            }
            close();
        }, 120);
    });
    toggle.addEventListener('click', () => {
        if (results.classList.contains('d-none')) {
            input.focus();
            render(input.value);
        } else {
            close();
            input.focus();
        }
    });
    select.addEventListener('change', () => {
        const selected = select.options[select.selectedIndex];
        input.value = select.value ? selected?.textContent?.trim() || select.value : '';
        input.setCustomValidity(wasRequired && !select.value ? 'Choose an option from the list.' : '');
    });
};

document.querySelectorAll('[data-searchable-select]').forEach(initialiseSearchableSelect);

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
    document.querySelectorAll('[data-public-service-panel]').forEach((panel) => {
        const active = panel.dataset.publicServicePanel === service;
        panel.classList.toggle('d-none', !active);
        panel.hidden = !active;
        panel.inert = !active;
    });
    document.querySelectorAll('[data-header-service]').forEach((link) => link.classList.toggle('active', link.dataset.headerService === service));
};

document.querySelectorAll('[data-public-service-tab]').forEach((tab) => tab.addEventListener('click', () => activatePublicService(tab.dataset.publicServiceTab)));

document.addEventListener('click', (event) => {
    const control = event.target.closest('[data-hotel-image-previous], [data-hotel-image-next], [data-hotel-image-dot]');
    if (!control) return;
    const slider = control.closest('[data-hotel-image-slider]');
    const slides = [...slider.querySelectorAll('[data-hotel-slide]')];
    const dots = [...slider.querySelectorAll('[data-hotel-image-dot]')];
    if (slides.length < 2) return;
    event.preventDefault();
    event.stopPropagation();
    const current = Math.max(0, slides.findIndex((slide) => slide.classList.contains('active')));
    const requestedIndex = Number(control.dataset.hotelImageIndex);
    const direction = control.hasAttribute('data-hotel-image-next') ? 1 : -1;
    const next = control.hasAttribute('data-hotel-image-dot')
        ? requestedIndex
        : (current + direction + slides.length) % slides.length;
    slides.forEach((slide, index) => slide.classList.toggle('active', index === next));
    dots.forEach((dot, index) => dot.classList.toggle('active', index === next));
});

document.querySelectorAll('[data-header-service]').forEach((link) => link.addEventListener('click', () => activatePublicService(link.dataset.headerService)));
const requestedPublicService = new URLSearchParams(window.location.search).get('service');
if (['flights', 'hotels', 'cars', 'visas', 'charter'].includes(requestedPublicService)) activatePublicService(requestedPublicService);
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

    let initialSegments = [];
    try {
        initialSegments = JSON.parse(searchRoot.querySelector('[data-initial-multi-city-segments]')?.textContent || '[]');
    } catch {
        initialSegments = [];
    }
    if (initialSegments.length >= 2) {
        initialSegments.forEach((segment) => addSegment({
            origin: segment.origin || '',
            originCode: segment.origin || '',
            destination: segment.destination || '',
            destinationCode: segment.destination || '',
            date: segment.departure_date || '',
        }));
    } else {
        addSegment({ date: departureInput.value });
        addSegment({ date: returnInput.value });
    }
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
        // Keep traveller counts and cabin enabled for multi-city searches. They
        // live inside the standard layout visually, but are still required in
        // the multi-city request payload.
        standardFields.querySelectorAll('[name="origin"], [name="destination"], [name="departure_date"], [name="return_date"], [data-airport-autocomplete], [data-flight-date-range]')
            .forEach((field) => field.disabled = multiCityActive);
        segmentContainer.querySelectorAll('input, select, button').forEach((field) => field.disabled = !multiCityActive);
        addSegmentButton.disabled = !multiCityActive || segmentContainer.querySelectorAll('[data-flight-segment]').length >= 6;
    };

    tripInputs.forEach((input) => input.addEventListener('change', () => setTripType(input.value)));
    setTripType(tripInputs.find((input) => input.checked)?.value || 'round_trip');

    // Homepage flight searches use a normal GET navigation. Handle that
    // navigation explicitly so a multi-city submission can never fall through
    // to another service form or lose its nested segment fields.
    if (form.hasAttribute('action')) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const tripType = tripTypeValue.value;

            if (tripType === 'multi_city') {
                const segments = [...segmentContainer.querySelectorAll('[data-flight-segment]')];
                let firstInvalid = null;
                segments.forEach((segment) => {
                    const origin = segment.querySelector('[data-segment-origin]');
                    const originCode = segment.querySelector('[data-segment-origin-code]');
                    const destination = segment.querySelector('[data-segment-destination]');
                    const destinationCode = segment.querySelector('[data-segment-destination-code]');
                    const date = segment.querySelector('[data-flight-segment-date]');
                    origin.setCustomValidity(originCode.value ? '' : 'Choose an origin from the airport suggestions.');
                    destination.setCustomValidity(destinationCode.value ? '' : 'Choose a destination from the airport suggestions.');
                    date.setCustomValidity(date.value ? '' : 'Choose a travel date.');
                    firstInvalid ||= [origin, destination, date].find(field => !field.checkValidity()) || null;
                });
                if (firstInvalid) {
                    firstInvalid.reportValidity();
                    firstInvalid.focus();
                    return;
                }
            } else if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const submit = form.querySelector('.flight-search-submit');
            submit.disabled = true;
            submit.querySelector('.spinner-border')?.classList.remove('d-none');
            const target = new URL(form.getAttribute('action'), window.location.origin);
            target.search = new URLSearchParams(new FormData(form)).toString();
            window.location.assign(target.toString());
        });
    }
});



const nextHotelCalendarDate = (value) => {
    const parts = String(value || '').split('-').map(Number);
    if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) return '';

    const date = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
    date.setUTCDate(date.getUTCDate() + 1);

    return [date.getUTCFullYear(), String(date.getUTCMonth() + 1).padStart(2, '0'), String(date.getUTCDate()).padStart(2, '0')].join('-');
};

document.querySelectorAll('[data-home-hotel-destination]').forEach((link) => {
    link.addEventListener('click', (event) => {
        // Preserve normal browser behaviour for opening the fallback URL in a
        // new tab/window. The server-rendered href already uses today/tomorrow.
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const hotelForm = document.querySelector('[data-public-service-panel="hotels"] [data-hotel-search]');
        if (!hotelForm) return;

        const target = new URL(link.href, window.location.origin);
        const checkIn = hotelForm.querySelector('[data-hotel-check-in]')?.value || '';
        const checkOut = hotelForm.querySelector('[data-hotel-check-out]')?.value || '';
        const effectiveCheckOut = checkOut || nextHotelCalendarDate(checkIn);

        if (checkIn) target.searchParams.set('check_in', checkIn);
        if (effectiveCheckOut) target.searchParams.set('check_out', effectiveCheckOut);

        ['adults', 'children', 'rooms'].forEach((field) => {
            const value = hotelForm.querySelector(`[name="${field}"]`)?.value;
            if (value !== undefined && value !== '') target.searchParams.set(field, value);
        });

        target.searchParams.set('destination_code', link.dataset.destinationCode || '');
        target.searchParams.set('destination_label', link.dataset.destinationLabel || '');
        target.searchParams.set('session_id', crypto.randomUUID?.() || `00000000-0000-4000-8000-${Date.now().toString().padStart(12, '0').slice(-12)}`);

        event.preventDefault();
        window.location.assign(target.toString());
    });
});

document.querySelectorAll('[data-destination-tabs]').forEach((section) => {
    const tabs = [...section.querySelectorAll('[data-destination-tab]')];
    const panels = [...section.querySelectorAll('[data-destination-panel]')];
    if (!tabs.length || !panels.length) return;

    const activateTab = (tab, moveFocus = false) => {
        const key = tab.dataset.destinationTab;

        tabs.forEach((candidate) => {
            const active = candidate === tab;
            candidate.classList.toggle('active', active);
            candidate.setAttribute('aria-selected', String(active));
            candidate.tabIndex = active ? 0 : -1;
        });

        panels.forEach((panel) => {
            const active = panel.dataset.destinationPanel === key;
            panel.hidden = !active;
            panel.classList.toggle('d-none', !active);
        });

        if (moveFocus) tab.focus();
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();

            let nextIndex = index;
            if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = tabs.length - 1;

            activateTab(tabs[nextIndex], true);
        });
    });
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
