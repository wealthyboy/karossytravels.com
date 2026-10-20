import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const source = readFileSync(new URL('../../resources/js/admin-flight-search.js', import.meta.url), 'utf8');

test('leg labels are black and retain the requested compact font size', () => {
    assert.match(css, /\.flight-compact-leg > small \{[^}]*color: #000;[^}]*font-size: var\(--small-font-size, \.7rem\);/);
});

test('decorative airplane icons face opposite ways for departure and return', () => {
    assert.ok(source.includes('bi bi-airplane-fill flight-leg-plane'));
    assert.ok(source.includes("${isReturn ? ' flight-leg-plane-return' : ''}"));
    assert.ok(source.includes('aria-hidden="true"></i><span>${label}</span>'));
    assert.match(css, /\.flight-leg-plane \{[^}]*transform: rotate\(90deg\);/);
    assert.match(css, /\.flight-leg-plane-return \{[^}]*transform: rotate\(-90deg\);/);
    assert.ok(source.includes("const isReturn = criteria.trip_type === 'round_trip' && index === 1;"));
});

test('both desktop and mobile reserve room for icon and label together', () => {
    const columns = [...css.matchAll(/\.flight-compact-leg \{[^}]*grid-template-columns: ([^;]+);/g)];
    assert.ok(columns.length >= 2);
    for (const [, value] of columns) assert.ok(value.startsWith('4.5rem '));
    assert.match(css, /\.flight-compact-leg > small \{[^}]*display: inline-flex;[^}]*white-space: nowrap;/);
});
