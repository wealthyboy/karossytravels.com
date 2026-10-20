import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createNavigationSubmitState } from '../../resources/js/navigation-submit-state.js';

function searchButton({ spinner = true } = {}) {
    const classes = new Set(['d-none']);
    const attributes = new Map();
    const icon = { classList: {
        contains: value => classes.has(value),
        remove: value => classes.delete(value),
        toggle: (value, enabled) => enabled ? classes.add(value) : classes.delete(value),
    } };
    return {
        disabled: false,
        querySelector: () => spinner ? icon : null,
        getAttribute: name => attributes.get(name) ?? null,
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: name => attributes.delete(name),
        spinnerHidden: () => classes.has('d-none'),
    };
}

test('returning through browser history clears the navigation spinner and enables another search', () => {
    const page = new EventTarget();
    const state = createNavigationSubmitState(page);
    const button = searchButton();
    state.start(button);
    assert.equal(button.disabled, true);
    assert.equal(button.spinnerHidden(), false);
    assert.equal(button.getAttribute('aria-busy'), 'true');

    const restored = new Event('pageshow');
    Object.defineProperty(restored, 'persisted', { value: true });
    page.dispatchEvent(restored);
    assert.equal(button.disabled, false);
    assert.equal(button.spinnerHidden(), true);
    assert.equal(button.getAttribute('aria-busy'), null);

    state.start(button);
    page.dispatchEvent(new Event('pageshow'));
    assert.equal(button.disabled, false);
    assert.equal(button.spinnerHidden(), true);
});

test('duplicate starts do not save the busy state as the original state', () => {
    const page = new EventTarget();
    const state = createNavigationSubmitState(page);
    const button = searchButton();
    state.start(button);
    state.start(button);
    page.dispatchEvent(new Event('pageshow'));
    assert.equal(button.disabled, false);
    assert.equal(button.spinnerHidden(), true);
});

test('restore only touches tracked navigation buttons, not an active AJAX button', () => {
    const page = new EventTarget();
    const state = createNavigationSubmitState(page);
    const flight = searchButton();
    const hotel = searchButton();
    const ajax = searchButton();
    ajax.disabled = true;
    state.start(flight);
    state.start(hotel);
    page.dispatchEvent(new Event('pageshow'));
    assert.equal(flight.disabled, false);
    assert.equal(hotel.disabled, false);
    assert.equal(ajax.disabled, true);
    // A subsequent pageshow must not override any new, unrelated button state.
    flight.disabled = true;
    page.dispatchEvent(new Event('pageshow'));
    assert.equal(flight.disabled, true);
});

test('missing spinners are safe and existing accessibility state is preserved', () => {
    const page = new EventTarget();
    const state = createNavigationSubmitState(page);
    const button = searchButton({ spinner: false });
    button.setAttribute('aria-busy', 'false');
    state.start(null);
    state.start(button);
    page.dispatchEvent(new Event('pageshow'));
    assert.equal(button.disabled, false);
    assert.equal(button.getAttribute('aria-busy'), 'false');
});

test('flight and hotel full-page search handlers use the history-aware loading state', () => {
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    assert.match(source, /const navigationSubmitState = createNavigationSubmitState\(window\)/);
    for (const selector of ['hotel-search-submit', 'flight-search-submit']) {
        assert.ok(source.includes(`querySelector('.${selector}');\n        ${selector === 'flight-search-submit' ? '    ' : ''}navigationSubmitState.start(submit);`));
    }
});
