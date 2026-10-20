import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { airlineLogoMarkup, handleAirlineLogoError } from '../../resources/js/airline-logos.js';

test('flight cards use a sharp SVG symbol beside the separate airline name', () => {
    const html = airlineLogoMarkup('qr', 'Qatar Airways');
    assert.ok(html.includes('full-color-logo/QR.svg'));
    assert.ok(!html.includes('full-color-lockup'));
    assert.ok(html.includes('https://pics.avs.io/320/320/QR.png'));
    assert.ok(html.includes('alt="Qatar Airways logo"'));
    assert.ok(html.includes('decoding="async"'));
    assert.ok(!html.includes('/80/80/'));
});

test('compact logos use the scalable symbol instead of a tiny full wordmark', () => {
    const html = airlineLogoMarkup('ET', 'Ethiopian Airlines', true);
    assert.ok(html.includes('class="airline-logo-wrap compact"'));
    assert.ok(html.includes('full-color-logo/ET.svg'));
    assert.ok(html.includes('https://pics.avs.io/160/160/ET.png'));
});

test('invalid airline codes never become an image URL and airline names are escaped', () => {
    assert.ok(!airlineLogoMarkup('../QR', 'Unknown').includes('<img'));
    assert.ok(airlineLogoMarkup('', '').includes('is-missing'));
    const html = airlineLogoMarkup('QR', '<img onerror="bad()">');
    assert.ok(html.includes('&lt;img onerror=&quot;bad()&quot;&gt; logo'));
});

test('a failed SVG retries the high-resolution PNG once, then shows the airline code', () => {
    const classes = new Set();
    const image = {
        matches: selector => selector === '[data-airline-logo]',
        dataset: { airlineLogoFallback: 'https://pics.avs.io/320/320/QR.png' },
        closest: () => ({ classList: { add: value => classes.add(value) } }),
    };
    handleAirlineLogoError(image);
    assert.equal(image.src, 'https://pics.avs.io/320/320/QR.png');
    assert.equal(image.dataset.airlineLogoFallback, undefined);
    assert.equal(classes.size, 0);
    handleAirlineLogoError(image);
    assert.ok(classes.has('is-missing'));
    handleAirlineLogoError({ matches: () => false });
});

test('logo sizing remains prominent and bounded, with a smaller filter-column variant', () => {
    const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
    assert.match(css, /\.airline-logo-wrap \{[^}]*height: 3\.5rem;[^}]*width: 3\.5rem;/);
    assert.match(css, /\.airline-logo-wrap\.compact \{[^}]*height: 2\.5rem;[^}]*width: 3rem;/);
    assert.match(css, /\.airline-logo-wrap img \{[^}]*object-fit: contain;/);
    assert.match(css, /\.flight-airline-filter-check \.airline-logo-wrap\.compact \{[^}]*width: 2rem;/);
});

test('Kenya Airways uses just its icon rather than the name-and-slogan wordmark', () => {
    const html = airlineLogoMarkup('KQ', 'Kenya Airways');
    assert.ok(html.includes('full-color-logo/KQ.svg'));
    assert.ok(!html.includes('full-color-lockup/KQ.svg'));
});

test('flight cards keep airline names and logos side by side at every breakpoint', () => {
    const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
    const rules = [...css.matchAll(/\.flight-compact-card \.flight-card-airline \{([^}]+)\}/g)];
    assert.ok(rules.length > 0);
    for (const [, declarations] of rules) {
        assert.match(declarations, /flex-direction: row;/);
        assert.doesNotMatch(declarations, /flex-direction: column;/);
    }
    assert.match(css, /\.flight-compact-card \.flight-card-airline strong \{[^}]*text-overflow: ellipsis;[^}]*white-space: nowrap;/);
    assert.match(css, /\.flight-compact-main \{[^}]*grid-template-columns: 11\.5rem /);
});

test('the airline section is a narrow fixed column rather than expanding into empty space', () => {
    const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
    const columns = [...css.matchAll(/\.flight-compact-main \{[^}]*grid-template-columns: ([^;]+);/g)];
    assert.ok(columns.length >= 2);
    for (const [, value] of columns) assert.ok(value.startsWith('11.5rem '));
    assert.match(css, /\.flight-compact-main \{[^}]*gap: \.5rem;[^}]*padding: \.4rem \.75rem;/);
    assert.match(css, /\.flight-compact-card \.flight-card-airline \.airline-logo-wrap \{ height: 3rem; width: 3rem; \}/);
});

test('airline rows have tighter vertical spacing on desktop and mobile without shrinking the logo', () => {
    const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
    assert.match(css, /\.flight-compact-itinerary \{[^}]*gap: \.35rem;[^}]*padding-left: \.75rem;/);
    assert.match(css, /\.flight-compact-route span, \.flight-compact-route em \{[^}]*line-height: 1\.3;/);
    assert.match(css, /\.flight-compact-card \.flight-card-price \{[^}]*padding: \.35rem \.6rem;/);
    assert.match(css, /\.flight-compact-card \.flight-card-airline \{[^}]*order: 1; padding: \.5rem \.75rem 0;/);
    assert.match(css, /\.flight-compact-itinerary \{[^}]*margin: \.4rem \.75rem 0;[^}]*padding: \.5rem 0 0;/);
});
