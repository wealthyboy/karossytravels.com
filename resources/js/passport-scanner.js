import { createWorker } from 'tesseract.js';
import { parseMrz, parseVisualPassportNames } from './passport-mrz';

let workerPromise;
let scanQueue = Promise.resolve();

const mrzParameters = {
    tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
    preserve_interword_spaces: '0',
    tessedit_pageseg_mode: '6',
};

const scannerWorker = () => {
    if (!workerPromise) {
        workerPromise = createWorker('eng', 1, {
            logger: message => document.dispatchEvent(new CustomEvent('passport-scan-progress', { detail: message })),
        }).then(async worker => {
            await worker.setParameters(mrzParameters);
            return worker;
        });
    }
    return workerPromise;
};

const passportCrop = (file, startRatio, endRatio, threshold = false, { leftRatio = 0, rightRatio = 1 } = {}) => new Promise((resolve, reject) => {
    const image = new Image();
    const url = URL.createObjectURL(file);
    image.onload = () => {
        const sourceX = Math.floor(image.naturalWidth * leftRatio);
        const sourceWidth = Math.floor(image.naturalWidth * rightRatio) - sourceX;
        const sourceY = Math.floor(image.naturalHeight * startRatio);
        const sourceHeight = Math.floor(image.naturalHeight * endRatio) - sourceY;
        const scale = Math.min(2.5, Math.max(1, 1800 / sourceWidth));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(sourceWidth * scale);
        canvas.height = Math.round(sourceHeight * scale);
        const context = canvas.getContext('2d', { willReadFrequently: true });
        context.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
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

const readPassport = async (file, travellerType, status) => {
    const worker = await scannerWorker();
    const crops = [
        [.80, 1, false],
        [.75, 1, true],
        [.68, 1, false],
        [.48, 1, false],
    ];
    let lastError = new Error('The passport code could not be found.');
    let fallback = null;
    const readings = [];
    const consider = parsed => {
        if (!fallback || parsed.__score > fallback.__score) fallback = parsed;
        return parsed.__verified;
    };

    for (let index = 0; index < crops.length; index += 1) {
        status.textContent = index === 0 ? 'Reading passport code…' : `Trying another passport area (${index + 1}/${crops.length})…`;
        const image = await passportCrop(file, crops[index][0], crops[index][1], crops[index][2]);
        const result = await worker.recognize(image);
        readings.push(result.data.text);
        try {
            const parsed = parseMrz(result.data.text, travellerType);
            if (consider(parsed)) return parsed;
        } catch (error) {
            lastError = error;
        }
    }

    // Names have no MRZ check digit. If their separators were unreadable,
    // use labelled printed names only when they also match this MRZ reading.
    if (fallback && (!fallback.first_name || !fallback.last_name)) {
        status.textContent = 'Reading printed passport names…';
        await worker.setParameters({ tessedit_char_whitelist: '', preserve_interword_spaces: '1', tessedit_pageseg_mode: '6' });
        try {
            const nameCrops = [
                [.60, .90, { leftRatio: .32, rightRatio: .88 }],
                [.45, .92, { leftRatio: .30, rightRatio: .95 }],
                [.08, .80, { leftRatio: .30, rightRatio: .95 }],
            ];
            for (const [start, end, bounds] of nameCrops) {
                const image = await passportCrop(file, start, end, false, bounds);
                const result = await worker.recognize(image);
                const names = parseVisualPassportNames(result.data.text);
                if (!names) continue;
                for (const text of readings) {
                    try {
                        const parsed = parseMrz(text, travellerType, names);
                        if (consider(parsed)) return parsed;
                    } catch (error) {
                        lastError = error;
                    }
                }
            }
        } finally {
            await worker.setParameters(mrzParameters);
        }
    }

    if (fallback) return fallback;
    throw lastError;
};

const recognizePassport = (file, travellerType, status) => {
    // All traveller cards share one worker, so keep OCR parameter changes
    // within a single scan instead of mixing MRZ and printed-name jobs.
    const reading = scanQueue.then(() => readPassport(file, travellerType, status));
    scanQueue = reading.catch(() => {});
    return reading;
};

const setField = (card, field, value) => {
    if (value === null || value === undefined) return;
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
            delete extracted.__score;
            Object.entries(extracted).forEach(([field, value]) => setField(card, field, value));
            status.className = `passport-scan-status ${verified ? 'is-success' : 'is-warning'}`;
            status.textContent = verified
                ? 'Passport details filled. Please review every field carefully.'
                : 'Some passport details could not be read reliably. Please review and complete the fields before continuing.';
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
