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

const numericMrz = value => value.replaceAll('O', '0').replaceAll('Q', '0').replaceAll('I', '1').replaceAll('L', '1');
const cleanName = value => value.replaceAll('<', ' ').replace(/\s+/g, ' ').trim();

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
        .map(line => {
            if (/^<[A-Z]{3}/.test(line)) return `P${line}`;
            if (/^P[A-Z]{3}/.test(line)) return `P<${line.slice(1)}`;
            return line;
        });
    const firstIndex = lines.findIndex(line => /^P<[A-Z]{3}/.test(line));
    if (firstIndex < 0 || !lines[firstIndex + 1]) throw new Error('The passport code could not be found.');
    const first = lines[firstIndex].padEnd(44, '<').slice(0, 44);
    const second = lines[firstIndex + 1].padEnd(44, '<').slice(0, 44);
    const passportNumber = second.slice(0, 9).replaceAll('<', '');
    const passportCheck = numericMrz(second[9]);
    const birthRaw = numericMrz(second.slice(13, 19));
    const birthCheck = numericMrz(second[19]);
    const expiryRaw = numericMrz(second.slice(21, 27));
    const expiryCheck = numericMrz(second[27]);
    if (Number(passportCheck) !== checkDigit(second.slice(0, 9))
        || Number(birthCheck) !== checkDigit(birthRaw)
        || Number(expiryCheck) !== checkDigit(expiryRaw)) {
        throw new Error('The passport code was not clear enough to read safely.');
    }
    const [surname = '', givenNames = ''] = first.slice(5).split('<<');
    const nationality = countries.alpha3ToAlpha2(second.slice(10, 13)) || '';
    const issuingCountry = countries.alpha3ToAlpha2(first.slice(2, 5)) || '';
    const genderCode = second[20];
    return {
        first_name: cleanName(givenNames),
        last_name: cleanName(surname),
        date_of_birth: mrzDate(birthRaw, 'birth', travellerType),
        gender: genderCode === 'M' ? 'male' : genderCode === 'F' ? 'female' : 'unspecified',
        nationality,
        passport_number: passportNumber,
        passport_country: issuingCountry,
        passport_expiry: mrzDate(expiryRaw, 'expiry', travellerType),
        title: genderCode === 'M' ? 'Mr' : genderCode === 'F' ? 'Ms' : '',
    };
};

const passportCrop = (file, startRatio, endRatio) => new Promise((resolve, reject) => {
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
            const contrasted = Math.max(0, Math.min(255, (grey - 128) * 1.45 + 128));
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
        [.48, .82],
        [.38, .92],
        [.48, 1],
    ];
    let lastError = new Error('The passport code could not be found.');

    for (let index = 0; index < crops.length; index += 1) {
        status.textContent = index === 0 ? 'Reading passport code…' : `Trying another passport area (${index + 1}/${crops.length})…`;
        const image = await passportCrop(file, crops[index][0], crops[index][1]);
        const result = await worker.recognize(image);
        try {
            return parseMrz(result.data.text, travellerType);
        } catch (error) {
            lastError = error;
        }
    }

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
            const fields = await recognizePassport(file, travellerType, status);
            Object.entries(fields).forEach(([field, value]) => setField(card, field, value));
            status.className = 'passport-scan-status is-success';
            status.textContent = 'Passport details filled. Please review every field carefully.';
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
