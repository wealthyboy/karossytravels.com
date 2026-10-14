document.querySelectorAll('[data-flight-promotion-fields]').forEach(fields => {
    const scope = fields.querySelector('[data-promotion-scope]');
    const airlines = fields.querySelector('[data-promotion-airlines]');
    const search = fields.querySelector('[data-promotion-airline-search]');
    const selectedSummary = fields.querySelector('[data-promotion-selected]');
    const syncSelected = () => {
        const selected = [...airlines.querySelectorAll('input[type="checkbox"]:checked')]
            .map(input => input.closest('label').querySelector('.form-check-label').textContent.trim());
        selectedSummary.textContent = `Selected: ${selected.join(', ') || 'None selected'}`;
    };
    const syncScope = () => {
        const all = scope.value === 'all';
        airlines.hidden = all;
        airlines.querySelectorAll('input').forEach(input => { input.disabled = all; });
    };
    scope.addEventListener('change', syncScope);
    airlines.addEventListener('change', syncSelected);
    search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        fields.querySelectorAll('[data-promotion-airline-option]').forEach(option => {
            option.hidden = !option.textContent.toLowerCase().includes(term);
        });
    });
    syncScope();
    syncSelected();
});

document.querySelectorAll('[data-flight-promotion-toggle]').forEach(toggle => {
    const fieldset = toggle.closest('form').querySelector('[data-flight-promotion-settings]');
    const sync = () => { fieldset.hidden = !toggle.checked; fieldset.disabled = !toggle.checked; };
    toggle.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('[data-featured-airline-name]').forEach(input => {
    const code = input.closest('form').querySelector('[data-featured-airline-code]');
    const options = [...document.getElementById(input.getAttribute('list')).options];
    input.addEventListener('input', () => {
        const selected = options.find(option => option.value.toLowerCase() === input.value.trim().toLowerCase());
        if (selected) code.value = selected.dataset.airlineCode;
    });
    code.addEventListener('input', () => {
        const selected = options.find(option => option.dataset.airlineCode === code.value.trim().toUpperCase());
        if (selected) input.value = selected.value;
    });
});
