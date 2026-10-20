const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
})[character]);

export function airlineLogoMarkup(code, name, compact = false) {
    const airline = String(code || '').trim().toUpperCase();
    const validCode = /^[A-Z0-9]{2}$/.test(airline);
    const classes = ['airline-logo-wrap', compact ? 'compact' : '', validCode ? '' : 'is-missing'].filter(Boolean).join(' ');
    const label = `${name || airline || 'Airline'} logo`;
    // Use the symbol everywhere: the airline's name is already rendered beside
    // it, so a full wordmark would repeat the same text and shrink the icon.
    const source = `https://assets.duffel.com/img/airlines/for-light-background/full-color-logo/${airline}.svg`;
    const fallback = `https://pics.avs.io/${compact ? '160/160' : '320/320'}/${airline}.png`;
    const image = validCode
        ? `<img src="${source}" data-airline-logo-fallback="${fallback}" alt="${escapeHtml(label)}" loading="lazy" decoding="async" data-airline-logo>`
        : '';
    return `<span class="${classes}">${image}<b>${escapeHtml(validCode ? airline : '—')}</b></span>`;
}

export function handleAirlineLogoError(image) {
    if (!image?.matches?.('[data-airline-logo]')) return;
    const fallback = image.dataset.airlineLogoFallback;
    if (fallback) {
        delete image.dataset.airlineLogoFallback;
        image.src = fallback;
        return;
    }
    image.closest('.airline-logo-wrap')?.classList.add('is-missing');
}
