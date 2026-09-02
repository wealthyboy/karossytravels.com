import { Modal } from 'bootstrap';

const page = document.querySelector('[data-public-hotel-results-page]');

if (page) {
    const results = page.querySelector('[data-hotel-results-content]');
    const message = page.querySelector('.hotel-search-message');
    const summary = page.querySelector('[data-hotel-results-summary]');
    const sort = page.querySelector('[data-hotel-sort]');
    const criteria = JSON.parse(page.querySelector('[data-hotel-search-criteria]')?.textContent || '{}');
    const modalElement = document.querySelector('#publicHotelSearchModal');
    const modal = Modal.getOrCreateInstance(modalElement);
    const status = modalElement.querySelector('[data-public-hotel-search-status]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let statusTimer = null;

    const applyHotelFilters = () => {
        const shell = results.querySelector('[data-public-filter-shell]');
        const list = results.querySelector('.hotel-offer-list');
        if (!shell || !list) return;

        const minimumRating = Number(shell.querySelector('input[name="hotel_rating"]:checked')?.value || 0);
        const breakfastOnly = shell.querySelector('input[name="hotel_breakfast"]')?.checked || false;
        const refundableOnly = shell.querySelector('input[name="hotel_refundable"]')?.checked || false;
        const sortValue = sort.querySelector('select')?.value || 'recommended';
        const cards = [...list.querySelectorAll('[data-hotel-result-card]')];

        cards.sort((first, second) => {
            if (sortValue === 'price_asc') return Number(first.dataset.price) - Number(second.dataset.price);
            if (sortValue === 'rating_desc') return Number(second.dataset.rating) - Number(first.dataset.rating);
            return Number(first.dataset.originalIndex) - Number(second.dataset.originalIndex);
        });

        cards.forEach(card => {
            const visible = Number(card.dataset.rating) >= minimumRating
                && (!breakfastOnly || card.dataset.breakfast === '1')
                && (!refundableOnly || card.dataset.refundable === '1');
            card.classList.toggle('d-none', !visible);
            list.append(card);
        });
    };

    const setLoadingCopy = () => {
        const messages = [
            `Checking rooms and live rates in ${criteria.destination_label || 'your destination'}…`,
            'Comparing room types and cancellation terms…',
            'Confirming taxes and displaying your total rate…',
            'Preparing the best available hotel options…',
        ];
        let index = 0;
        status.textContent = messages[index];
        statusTimer = window.setInterval(() => {
            index = Math.min(index + 1, messages.length - 1);
            status.textContent = messages[index];
        }, 1700);
    };

    const showError = () => {
        message.className = 'hotel-search-message alert alert-danger hotel-search-error';
        message.innerHTML = '<div><i class="bi bi-exclamation-circle"></i><span><strong>Temporary network issue</strong><small>We could not connect to the hotel network. Please check your connection and try again shortly.</small></span></div><button class="btn btn-karossy btn-sm" type="button" data-retry-hotel-search><i class="bi bi-arrow-clockwise"></i> Try again</button>';
    };

    const performSearch = async () => {
        const startedAt = Date.now();
        message.className = 'hotel-search-message d-none';
        message.replaceChildren();
        results.classList.add('d-none');
        results.setAttribute('aria-busy', 'true');
        sort.classList.add('d-none');
        setLoadingCopy();

        try {
            const response = await fetch(page.dataset.searchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(criteria),
            });
            const body = await response.json();
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Hotel search failed.');

            const propertyCount = Number(body.meta?.property_count || 0);
            const offerCount = Number(body.meta?.offer_count || 0);
            results.innerHTML = body.data?.html || '';
            results.querySelectorAll('.hotel-result-enter').forEach((card, index) => {
                card.style.setProperty('--hotel-result-index', index);
                card.dataset.originalIndex = index;
            });
            results.classList.remove('d-none');
            results.setAttribute('aria-busy', 'false');
            sort.classList.toggle('d-none', propertyCount < 2);
            summary.textContent = `${propertyCount} live ${propertyCount === 1 ? 'property' : 'properties'} · ${offerCount} ${offerCount === 1 ? 'rate' : 'rates'} · Taxes and fees included`;

            const minimumModalTime = Math.max(0, 700 - (Date.now() - startedAt));
            if (minimumModalTime) await new Promise(resolve => window.setTimeout(resolve, minimumModalTime));
            modal.hide();
            results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            modal.hide();
            showError();
        } finally {
            if (statusTimer) window.clearInterval(statusTimer);
            statusTimer = null;
        }
    };

    page.addEventListener('click', event => {
        if (event.target.closest('[data-retry-hotel-search]')) {
            modal.show();
            performSearch();
            return;
        }

        if (event.target.closest('[data-clear-hotel-filters]')) {
            results.querySelectorAll('.public-filter-panel input').forEach(input => { input.checked = false; });
            applyHotelFilters();
        }
    });

    page.addEventListener('change', event => {
        if (event.target.matches('.public-filter-panel input, [data-hotel-sort] select')) applyHotelFilters();
    });

    modalElement.addEventListener('shown.bs.modal', performSearch, { once: true });
    window.requestAnimationFrame(() => window.requestAnimationFrame(() => modal.show()));
}
