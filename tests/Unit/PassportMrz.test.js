import test from 'node:test';
import assert from 'node:assert/strict';
import { parseMrz, parseVisualPassportNames } from '../../resources/js/passport-mrz.js';

// Fictional names and the ICAO example document number; no customer data.
const fixture = ({ names = 'SMITH<<ALICE<MARIA', issuer = 'GBR', nationality = issuer, birth = '7408122' } = {}) => {
    const first = `P<${issuer}${names}`.padEnd(44, '<').slice(0, 44);
    const second = `L898902C36${nationality}${birth}F3211083<<<<<<<<<<<<<<00`;
    assert.equal(first.length, 44);
    assert.equal(second.length, 44);
    return `${first}\n${second}`;
};

test('maps clear MRZ names into first, middle and last name', () => {
    const result = parseMrz(fixture(), 'ADT');
    assert.equal(result.first_name, 'ALICE');
    assert.equal(result.middle_name, 'MARIA');
    assert.equal(result.last_name, 'SMITH');
    assert.equal(result.nationality, 'GB');
    assert.equal(result.passport_country, 'GB');
    assert.equal(result.date_of_birth, '1974-08-12');
    assert.equal(result.passport_expiry, '2032-11-08');
    assert.equal(result.__verified, true);
});

for (const [country, expected] of [['NGA', 'NG'], ['USA', 'US'], ['CAN', 'CA'], ['FRA', 'FR'], ['IND', 'IN']]) {
    test(`preserves correct scans for ${country} passports`, () => {
        const result = parseMrz(fixture({ issuer: country }), 'ADT');
        assert.equal(result.first_name, 'ALICE');
        assert.equal(result.last_name, 'SMITH');
        assert.equal(result.nationality, expected);
        assert.equal(result.passport_country, expected);
        assert.equal(result.__verified, true);
    });
}

test('preserves compound surnames and C/L/S/K letters in genuine names', () => {
    const result = parseMrz(fixture({ names: 'MCDONALD<CLARKE<<CLARA<MARC' }), 'ADT');
    assert.equal(result.last_name, 'MCDONALD CLARKE');
    assert.equal(result.first_name, 'CLARA');
    assert.equal(result.middle_name, 'MARC');
    assert.equal(result.__verified, true);
});

test('does not report unreadable name separators as a verified scan', () => {
    const result = parseMrz(fixture({ names: 'SMITH<C<ALICEC<LLLLLLLLLLLLLLLLL' }), 'ADT');
    assert.equal(result.__verified, false);
    assert.equal(result.first_name, '');
    assert.equal(result.last_name, '');
    assert.equal(result.passport_number, 'L898902C3');
    assert.equal(result.date_of_birth, '1974-08-12');
});

test('rejects OCR filler letters even when a double separator is present', () => {
    const result = parseMrz(fixture({ names: 'SMITH<<ALICELCCLLLLLLLLLLLLLLK' }), 'ADT');
    assert.equal(result.__verified, false);
    assert.equal(result.first_name, '');
    assert.equal(result.last_name, '');
});

test('corrects digit lookalikes in country fields without changing numbers', () => {
    const result = parseMrz(fixture({ issuer: 'G8R', nationality: '6BR' }), 'ADT');
    assert.equal(result.nationality, 'GB');
    assert.equal(result.passport_country, 'GB');
    assert.equal(result.passport_number, 'L898902C3');
    assert.equal(result.__verified, true);
});

test('leaves an unknown nationality empty instead of assuming Nigeria', () => {
    const result = parseMrz(fixture({ nationality: 'ZZZ' }), 'ADT');
    assert.equal(result.nationality, '');
    assert.equal(result.passport_country, 'GB');
    assert.equal(result.__verified, false);
});

test('reads bilingual printed labels and common label OCR errors', () => {
    const result = parseVisualPassportNames('Surmame/Nem (1)\nSMITH\nGiven names/Prénoms (2)\nALICE MARIA    —\nNationality/Nationalité (3)\nBRITISH CITIZEN');
    assert.deepEqual(result, { first_name: 'ALICE', middle_name: 'MARIA', last_name: 'SMITH' });
});

test('reads names on the same line as their printed labels', () => {
    assert.deepEqual(parseVisualPassportNames('Surname: SMITH\nGiven names: ALICE'), {
        first_name: 'ALICE', middle_name: '', last_name: 'SMITH',
    });
});

test('prefers the printed name line over a stray letter beside its label', () => {
    assert.deepEqual(parseVisualPassportNames('Surmame/Nem (1)    A\nSMITH\nGiven names/Prénoms (2)\nALICE   —\nNationality/Nationalité (3)'), {
        first_name: 'ALICE', middle_name: '', last_name: 'SMITH',
    });
});

test('printed substrings cannot replace complete readable MRZ names', () => {
    const result = parseMrz(fixture({ names: 'SMITHSON<<ALICE<MARIA' }), 'ADT', {
        first_name: 'ALI', middle_name: '', last_name: 'SMITH',
    });
    assert.equal(result.last_name, 'SMITHSON');
    assert.equal(result.first_name, 'ALICE');
    assert.equal(result.middle_name, 'MARIA');
});

test('uses printed names only when they agree with the damaged MRZ', () => {
    const names = { first_name: 'ALICE', middle_name: '', last_name: 'SMITH' };
    const result = parseMrz(fixture({ names: 'SMITH<C<ALICEC<LLLLLLLLLLLLLLLLL' }), 'ADT', names);
    assert.equal(result.first_name, 'ALICE');
    assert.equal(result.middle_name, '');
    assert.equal(result.last_name, 'SMITH');
    assert.equal(result.__verified, true);
});

test('does not guess a name boundary when printed names disagree', () => {
    const result = parseMrz(fixture({ names: 'SMITH<C<ALICEC<LLLLLLLLLLLLLLLLL' }), 'ADT', {
        first_name: 'BOB', middle_name: '', last_name: 'JONES',
    });
    assert.equal(result.first_name, '');
    assert.equal(result.last_name, '');
    assert.equal(result.__verified, false);
});

test('rejects a truncated printed surname even when MRZ separators are damaged', () => {
    const result = parseMrz(fixture({ names: 'SMITHSON<C<ALICEC<LLLLLLLLLLLL' }), 'ADT', {
        first_name: 'ALICE', middle_name: '', last_name: 'SMITH',
    });
    assert.equal(result.first_name, '');
    assert.equal(result.last_name, '');
    assert.equal(result.__verified, false);
});

test('rejects missing given-name components in a printed fallback', () => {
    const result = parseMrz(fixture({ names: 'SMITH<C<ALICE<MARIA<LLLLLLLLL' }), 'ADT', {
        first_name: 'ALICE', middle_name: '', last_name: 'SMITH',
    });
    assert.equal(result.first_name, '');
    assert.equal(result.__verified, false);
});

test('a valid name cannot make a failed numeric check pass', () => {
    const result = parseMrz(fixture({ birth: '7408123' }), 'ADT');
    assert.equal(result.first_name, 'ALICE');
    assert.equal(result.__verified, false);
});

test('preserves separate nationality and issuing-country values', () => {
    const result = parseMrz(fixture({ issuer: 'USA', nationality: 'GBR' }), 'ADT');
    assert.equal(result.nationality, 'GB');
    assert.equal(result.passport_country, 'US');
});

test('continues to read two MRZ lines merged by OCR', () => {
    const result = parseMrz(fixture().replace('\n', ''), 'ADT');
    assert.equal(result.first_name, 'ALICE');
    assert.equal(result.last_name, 'SMITH');
    assert.equal(result.__verified, true);
});
