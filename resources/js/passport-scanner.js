import { createWorker } from 'tesseract.js';
import countries from 'i18n-iso-countries';
import enCountries from 'i18n-iso-countries/langs/en.json';

countries.registerLocale(enCountries);

let workerPromise;

const scannerWorker = () => {
    if (!workerPromise) {
        workerPromise = createWorker('eng', 1, {
            logger: message => document.dispatchEvent(new CustomEvent('passport-scan-progress', { detail: message })),
        }).then(async worker => {
            await worker.setParameters({
                tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
                preserve_interword_spaces: '0',
                tessedit_pageseg_mode: '6',
            });
            return worker;
        });
    }
    return workerPromise;
};

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

const mrzGivenNameParts = value => {
    const exactParts = value.split('<').map(cleanName).filter(Boolean);
    if (exactParts.length > 1) return exactParts;

    // On low-contrast passport paper, OCR can turn the MRZ separators into
    // C/S and the trailing <<<<<< filler into a long run of L characters.
    // Use this only for an unverified fallback; verified MRZ text is untouched.
    const filler = value.match(/([A-Z])\1{3,}.*$/);
    if (!filler) return exactParts;
    const withoutFiller = value.slice(0, filler.index).replace(/[CSLK]$/, '');
    const possibleSeparators = [...withoutFiller]
        .map((character, index) => ({ character, index }))
        .filter(({ character, index }) => /[CSLK]/.test(character) && index >= 3 && index <= withoutFiller.length - 3);
    const separator = possibleSeparators.sort((a, b) => Math.abs(a.index - withoutFiller.length / 2) - Math.abs(b.index - withoutFiller.length / 2))[0];
    if (!separator) return [cleanName(withoutFiller)].filter(Boolean);

    return [
        cleanName(withoutFiller.slice(0, separator.index)),
        cleanName(withoutFiller.slice(separator.index + 1)),
    ].filter(Boolean);
};

// OCR regularly reads digits in an MRZ as similar-looking letters. Keep the
// original value when its checksum is valid, otherwise try the digit-safe
// version and only accept it when the passport's own check digit confirms it.
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

const parseMrz = (text, travellerType) => {
    const lines = text.toUpperCase().split(/\r?\n/)
        .map(line => line.replace(/[^A-Z0-9<]/g, ''))
        .filter(line => line.length >= 30)
        .flatMap(line => /^P</.test(line) && line.length >= 80 ? [line.slice(0, 44), line.slice(44, 88)] : [line])
        .map(line => {
            if (/^<[A-Z]{3}/.test(line)) return `P${line}`;
            if (/^P[A-Z]{3}/.test(line)) return `P<${line.slice(1)}`;
            return line;
        });
    const firstIndex = lines.findIndex(line => /^P<[A-Z]{3}/.test(line));
    if (firstIndex < 0 || !lines[firstIndex + 1]) throw new Error('The passport code could not be found.');
    const first = lines[firstIndex].padEnd(44, '<').slice(0, 44);
    const second = lines[firstIndex + 1].padEnd(44, '<').slice(0, 44);
    const passportRaw = second.slice(0, 9);
    const checkedPassport = checkedMrzValue(passportRaw, second[9]);
    const checkedBirth = checkedMrzValue(second.slice(13, 19), second[19], true);
    const checkedExpiry = checkedMrzValue(second.slice(21, 27), second[27], true);
    const passportValue = checkedPassport || `${passportRaw.slice(0, 1)}${numericMrz(passportRaw.slice(1))}`;
    const birthRaw = checkedBirth || numericMrz(second.slice(13, 19));
    const expiryRaw = checkedExpiry || numericMrz(second.slice(21, 27));
    const birthDate = mrzDate(birthRaw, 'birth', travellerType);
    const expiryDate = mrzDate(expiryRaw, 'expiry', travellerType);
    if (!passportValue.replaceAll('<', '') || !birthDate || !expiryDate) throw new Error('The passport details could not be read from this image.');
    const passportNumber = passportValue.replaceAll('<', '');
    const [surname = '', givenNames = ''] = first.slice(5).split('<<');
    const verified = Boolean(checkedPassport && checkedBirth && checkedExpiry);
    const givenNameParts = mrzGivenNameParts(givenNames);
    const nationality = countries.alpha3ToAlpha2(second.slice(10, 13)) || '';
    const issuingCountry = countries.alpha3ToAlpha2(first.slice(2, 5)) || '';
    const genderCode = second[20];
    return {
        first_name: givenNameParts.shift() || cleanName(givenNames),
        middle_name: givenNameParts.join(' '),
        last_name: cleanName(surname),
        date_of_birth: birthDate,
        gender: genderCode === 'M' ? 'male' : genderCode === 'F' ? 'female' : 'unspecified',
        nationality,
        passport_number: passportNumber,
        passport_country: issuingCountry,
        passport_expiry: expiryDate,
        title: genderCode === 'M' ? 'Mr' : genderCode === 'F' ? 'Ms' : '',
        __verified: verified,
    };
};

const passportCrop = (file, startRatio, endRatio, threshold = false) => new Promise((resolve, reject) => {
    const image = new Image();
    const url = URL.createObjectURL(file);
    image.onload = () => {
        const sourceY = Math.floor(image.naturalHeight * startRatio);
        const sourceHeight = Math.floor(image.naturalHeight * endRatio) - sourceY;
        const scale = Math.min(2.5, Math.max(1, 1800 / image.naturalWidth));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(image.naturalWidth * scale);
        canvas.height = Math.round(sourceHeight * scale);
        const context = canvas.getContext('2d', { willReadFrequently: true });
        context.drawImage(image, 0, sourceY, image.naturalWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
        for (let index = 0; index < pixels.data.length; index += 4) {
            const grey = pixels.data[index] * .299 + pixels.data[index + 1] * .587 + pixels.data[index + 2] * .114;
            const contrasted = threshold
                ? (grey > 155 ? 255 : 0)
                : Math.max(0, Math.min(255, (grey - 128) * 1.7 + 128));
            pixels.data[index] = pixels.data[index + 1] = pixels.data[index + 2] = contrasted;
        }
        context.putImageData(pixels, 0, 0);
        URL.revokeObjectURL(url);
        canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('The passport image could not be prepared.')), 'image/jpeg', .92);
    };
    image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('The passport image could not be opened.')); };
    image.src = url;
});

const recognizePassport = async (file, travellerType, status) => {
    const worker = await scannerWorker();
    const crops = [
        [.80, 1, false],
        [.75, 1, true],
        [.68, 1, false],
        [.48, 1, false],
    ];
    let lastError = new Error('The passport code could not be found.');
    let fallback = null;

    for (let index = 0; index < crops.length; index += 1) {
        status.textContent = index === 0 ? 'Reading passport code…' : `Trying another passport area (${index + 1}/${crops.length})…`;
        const image = await passportCrop(file, crops[index][0], crops[index][1], crops[index][2]);
        const result = await worker.recognize(image);
        try {
            const parsed = parseMrz(result.data.text, travellerType);
            if (parsed.__verified) return parsed;
            fallback ??= parsed;
        } catch (error) {
            lastError = error;
        }
    }

    if (fallback) return fallback;
    throw lastError;
};

const setField = (card, field, value) => {
    if (!value) return;
    const input = card.querySelector(`[name$="[${field}]"]`);
    if (!input) return;
    if (input._flatpickr) input._flatpickr.setDate(value, true, 'Y-m-d');
    else input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
};

document.querySelectorAll('[data-passport-scanner]').forEach(scanner => {
    const card = scanner.closest('.traveller-card');
    const input = scanner.querySelector('[data-passport-image]');
    const status = scanner.querySelector('[data-passport-scan-status]');
    const button = scanner.querySelector('[data-scan-passport]');
    let progressHandler;
    button?.addEventListener('click', () => input?.click());
    input?.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file || !card) return;
        button.disabled = true;
        scanner.classList.add('is-scanning');
        status.className = 'passport-scan-status';
        status.textContent = 'Preparing passport image…';
        progressHandler = event => {
            if (event.detail?.status === 'recognizing text') status.textContent = `Reading passport ${Math.round((event.detail.progress || 0) * 100)}%…`;
        };
        document.addEventListener('passport-scan-progress', progressHandler);
        try {
            const travellerType = card.querySelector('[name$="[type]"]')?.value || 'ADT';
            const extracted = await recognizePassport(file, travellerType, status);
            const verified = extracted.__verified;
            delete extracted.__verified;
            Object.entries(extracted).forEach(([field, value]) => setField(card, field, value));
            status.className = `passport-scan-status ${verified ? 'is-success' : 'is-warning'}`;
            status.textContent = verified
                ? 'Passport details filled. Please review every field carefully.'
                : 'Passport details filled, but some characters need your review before continuing.';
        } catch (error) {
            status.className = 'passport-scan-status is-error';
            status.textContent = `${error.message} Retake the photo with the two code lines fully visible and well lit.`;
        } finally {
            document.removeEventListener('passport-scan-progress', progressHandler);
            scanner.classList.remove('is-scanning');
            button.disabled = false;
            input.value = '';
        }
    });
});
