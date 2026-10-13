import countries from 'i18n-iso-countries';

const checkDigit = value => {
    const weights = [7, 3, 1];
    const characterValue = character => character === '<' ? 0 : (/\d/.test(character) ? Number(character) : character.charCodeAt(0) - 55);
    return [...value].reduce((total, character, index) => total + characterValue(character) * weights[index % 3], 0) % 10;
};

const numericMrz = value => value
    .replace(/[OQD]/g, '0')
    .replace(/[IL]/g, '1')
    .replaceAll('Z', '2')
    .replaceAll('S', '5')
    .replaceAll('G', '6')
    .replaceAll('B', '8');

const cleanName = value => value.replaceAll('<', ' ').replace(/\s+/g, ' ').trim();
const comparableName = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().replace(/[^A-Z]/g, '');

const countryCode = value => {
    const exact = countries.alpha3ToAlpha2(value);
    if (exact) return exact;

    // Country positions contain letters, unlike the adjacent numeric fields.
    // Correct only digit lookalikes that resolve to one recognised country.
    const lookalikes = { '0': ['O'], '1': ['I', 'L'], '2': ['Z'], '5': ['S'], '6': ['G'], '8': ['B'] };
    const candidates = [...value].reduce((prefixes, character) => prefixes.flatMap(prefix => (lookalikes[character] || [character]).map(letter => prefix + letter)), ['']);
    const matches = [...new Set(candidates.map(candidate => countries.alpha3ToAlpha2(candidate)).filter(Boolean))];
    return matches.length === 1 ? matches[0] : '';
};

const machineNames = value => {
    const empty = { first_name: '', middle_name: '', last_name: '' };
    // A run of these characters at the end is a common misreading of <<<<<.
    // Do not invent a name split or strip letters from a genuine name.
    if (/([CLSK])\1{3,}[CLSK<]*$/.test(value)) return empty;
    const match = value.match(/^([A-Z]+(?:<[A-Z]+)*)<<([A-Z]+(?:<[A-Z]+)*)?<*$/);
    if (!match) return empty;
    const givenParts = (match[2] || '').split('<').filter(Boolean);
    return {
        first_name: givenParts.shift() || '',
        middle_name: givenParts.join(' '),
        last_name: cleanName(match[1]),
    };
};

const printedNamesMatch = (names, nameArea) => {
    if (!names?.first_name || !names?.last_name) return false;
    const surname = comparableName(names.last_name);
    const givenNames = comparableName(`${names.first_name} ${names.middle_name || ''}`);
    const exactNames = machineNames(nameArea);
    if (exactNames.first_name && exactNames.last_name) {
        return comparableName(exactNames.last_name) === surname && comparableName(`${exactNames.first_name} ${exactNames.middle_name}`) === givenNames;
    }
    // Match the whole name area: a shortened printed name must not merely
    // match a prefix of the actual surname or given names.
    const namePattern = value => value.split(/[\s-]+/).map(comparableName).filter(Boolean).join('[<CSLK]+');
    return Boolean(surname && givenNames && new RegExp(`^${namePattern(names.last_name)}[<CSLK]+${namePattern(`${names.first_name} ${names.middle_name || ''}`)}[<CSLK]*$`).test(nameArea));
};

export const parseVisualPassportNames = text => {
    const lines = text.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
    const surnameLabel = /\bSUR[NM]AMES?\b|\b(?:LAST|FAMILY)[\s_-]*NAMES?\b/i;
    const givenLabel = /\b(?:GIVEN|FIRST|FORE)[\s_-]*NAMES?\b|\bPRENOMS?\b/i;
    const fieldLabel = /surname|surmame|given|first.name|family.name|nationality|birth|expiry|expiration|sex|passport|authority|issue/i;
    const namePattern = /^[\p{L}][\p{L}\p{M}'’\-]*(?: [\p{L}][\p{L}\p{M}'’\-]*)*$/u;
    const cleanPrintedName = line => line.replace(/[—–].*$/, '').replace(/\s+-\s.*$/, '').replace(/\s+/g, ' ').trim().toUpperCase();
    const readLabel = label => {
        const index = lines.findIndex(line => label.test(line.normalize('NFD').replace(/[\u0300-\u036f]/g, '')));
        if (index < 0) return '';
        const labelLine = lines[index];
        const labelMatch = labelLine.normalize('NFD').replace(/[\u0300-\u036f]/g, '').match(label);
        let inline = '';
        if (labelLine.includes(')')) inline = labelLine.slice(labelLine.lastIndexOf(')') + 1);
        else if (labelLine.includes(':')) inline = labelLine.slice(labelLine.lastIndexOf(':') + 1);
        else if (labelMatch) {
            const remainder = labelLine.slice(labelMatch.index + labelMatch[0].length).trim();
            if (remainder && !remainder.startsWith('/')) inline = remainder;
        }
        inline = cleanPrintedName(inline);
        for (const line of lines.slice(index + 1, index + 4)) {
            if (fieldLabel.test(line)) break;
            const name = cleanPrintedName(line);
            if (namePattern.test(name)) return name;
        }
        return namePattern.test(inline) ? inline : '';
    };
    const lastName = readLabel(surnameLabel);
    const givenParts = readLabel(givenLabel).split(' ').filter(Boolean);
    if (!lastName || !givenParts.length) return null;
    return { first_name: givenParts.shift(), middle_name: givenParts.join(' '), last_name: lastName };
};

const checkedMrzValue = (value, rawCheckDigit, numeric = false) => {
    const check = numericMrz(rawCheckDigit);
    if (!/^\d$/.test(check)) return null;
    const candidates = numeric
        ? [numericMrz(value)]
        : [value, `${value.slice(0, 1)}${numericMrz(value.slice(1))}`];
    return [...new Set(candidates)].find(candidate => checkDigit(candidate) === Number(check)) || null;
};

const mrzDate = (value, type, travellerType) => {
    const digits = numericMrz(value);
    if (!/^\d{6}$/.test(digits)) return null;
    const year = Number(digits.slice(0, 2));
    const month = Number(digits.slice(2, 4));
    const day = Number(digits.slice(4, 6));
    const currentYear = new Date().getFullYear();
    let fullYear = 2000 + year;
    if (type === 'birth') {
        const age = currentYear - fullYear;
        if (fullYear > currentYear || (travellerType === 'ADT' && age < 12)) fullYear -= 100;
    }
    const date = new Date(Date.UTC(fullYear, month - 1, day));
    if (date.getUTCFullYear() !== fullYear || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;
    return `${fullYear}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

export const parseMrz = (text, travellerType, printedNames = null) => {
    const lines = text.toUpperCase().split(/\r?\n/)
        .map(line => line.replace(/[^A-Z0-9<]/g, ''))
        .filter(line => line.length >= 30)
        .flatMap(line => /^P</.test(line) && line.length >= 80 ? [line.slice(0, 44), line.slice(44, 88)] : [line])
        .map(line => {
            if (/^<[A-Z0-9]{3}/.test(line)) return `P${line}`;
            if (/^P[A-Z0-9]{3}/.test(line)) return `P<${line.slice(1)}`;
            return line;
        });
    const firstIndex = lines.findIndex(line => /^P<[A-Z0-9]{3}/.test(line));
    if (firstIndex < 0 || !lines[firstIndex + 1]) throw new Error('The passport code could not be found.');
    const first = lines[firstIndex].padEnd(44, '<').slice(0, 44);
    const second = lines[firstIndex + 1].padEnd(44, '<').slice(0, 44);
    const passportRaw = second.slice(0, 9);
    const checkedPassport = checkedMrzValue(passportRaw, second[9]);
    const checkedBirth = checkedMrzValue(second.slice(13, 19), second[19], true);
    const checkedExpiry = checkedMrzValue(second.slice(21, 27), second[27], true);
    const passportValue = checkedPassport || `${passportRaw.slice(0, 1)}${numericMrz(passportRaw.slice(1))}`;
    const birthDate = mrzDate(checkedBirth || numericMrz(second.slice(13, 19)), 'birth', travellerType);
    const expiryDate = mrzDate(checkedExpiry || numericMrz(second.slice(21, 27)), 'expiry', travellerType);
    if (!passportValue.replaceAll('<', '') || !birthDate || !expiryDate) throw new Error('The passport details could not be read from this image.');
    const nameArea = first.slice(5);
    const names = printedNamesMatch(printedNames, nameArea) ? printedNames : machineNames(nameArea);
    const nationality = countryCode(second.slice(10, 13));
    const issuingCountry = countryCode(first.slice(2, 5));
    const genderCode = second[20];
    const checks = [checkedPassport, checkedBirth, checkedExpiry, names.first_name, names.last_name, nationality, issuingCountry, ['M', 'F', '<'].includes(genderCode)];
    return {
        ...names,
        date_of_birth: birthDate,
        gender: genderCode === 'M' ? 'male' : genderCode === 'F' ? 'female' : 'unspecified',
        nationality,
        passport_number: passportValue.replaceAll('<', ''),
        passport_country: issuingCountry,
        passport_expiry: expiryDate,
        title: genderCode === 'M' ? 'Mr' : genderCode === 'F' ? 'Ms' : '',
        __verified: checks.every(Boolean),
        __score: checks.filter(Boolean).length,
    };
};
