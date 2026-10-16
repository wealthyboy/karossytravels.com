document.querySelectorAll('[data-service-enquiry]').forEach(form => {
    const field = name => form.elements.namedItem(name);
    const errorSummary = form.querySelector('[data-enquiry-error]');
    const button = form.querySelector('[type="submit"]');
    const spinner = form.querySelector('[data-enquiry-spinner]');
    const visibleControl = input => input.closest('.searchable-select')?.querySelector('.searchable-select-input') || input;
    const showFieldError = (name, message = '') => {
        const wrapper = [...form.querySelectorAll('[data-enquiry-field]')].find(el => el.dataset.enquiryField === name);
        if (!wrapper) return;
        const error = wrapper.querySelector('[data-enquiry-field-error]');
        if (error) { error.textContent = message; error.hidden = !message; }
        wrapper.querySelectorAll('input, select, textarea').forEach(input => {
            input.classList.toggle('is-invalid', Boolean(message));
            input.setAttribute('aria-invalid', String(Boolean(message)));
        });
    };
    const clearErrors = () => {
        errorSummary.hidden = true;
        form.querySelectorAll('[data-enquiry-field]').forEach(el => showFieldError(el.dataset.enquiryField));
    };

    if (form.dataset.serviceEnquiry === 'car_hire') {
        const returnTypes = ['Round-Trip Transfer', 'Daily Car Hire', 'Chauffeur-Driven Car'];
        const updateReturn = () => {
            const required = returnTypes.includes(field('hire_type').value);
            field('return_end_date').required = required || Boolean(field('return_end_time').value);
            field('return_end_time').required = required || Boolean(field('return_end_date').value);
            field('return_end_date').min = field('pickup_date').value || field('pickup_date').min;
            ['return_end_date', 'return_end_time'].forEach(name => {
                form.querySelector(`[data-enquiry-field="${name}"] .enquiry-optional`).textContent = field(name).required ? ' (required)' : ' (if applicable)';
            });
        };
        ['hire_type', 'pickup_date', 'return_end_date', 'return_end_time'].forEach(name => field(name).addEventListener('change', updateReturn));
        updateReturn();
    } else if (form.dataset.serviceEnquiry === 'visa_enquiry') {
        const options = JSON.parse(form.querySelector('[data-visa-enquiry-options]').textContent);
        const updatePurpose = () => {
            const purpose = field('purpose_of_travel').value;
            const select = field('visa_type');
            const previous = select.value;
            const types = options.types[purpose] || [];
            select.replaceChildren(new Option(purpose ? 'Select a visa type' : 'Select a purpose of travel first', ''));
            types.forEach(type => select.add(new Option(type, type)));
            select.disabled = !purpose;
            if (types.includes(previous)) select.value = previous;
            else if (types.length === 3) select.value = types[0];
            const question = options.documents[purpose] || ['Do you have any supporting documents for this trip?', 'For example, an invitation letter, admission letter or employment letter.'];
            form.querySelector('[data-visa-document-label]').textContent = question[0];
            form.querySelector('[data-visa-document-hint]').textContent = question[1];
        };
        field('purpose_of_travel').addEventListener('change', updatePurpose);
        updatePurpose();
        form.querySelectorAll('[data-enquiry-conditional]').forEach(wrapper => {
            const name = wrapper.dataset.enquiryConditional;
            const update = () => {
                const visible = field(name).value === 'Yes';
                wrapper.hidden = !visible;
                wrapper.querySelectorAll('input').forEach(input => { input.disabled = !visible; });
            };
            form.querySelectorAll(`[name="${name}"]`).forEach(input => input.addEventListener('change', update));
            update();
        });
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled) return;
        clearErrors();
        let firstInvalid = null;
        [...form.elements].filter(input => ['INPUT', 'SELECT', 'TEXTAREA'].includes(input.tagName) && !input.disabled).forEach(input => {
            if (!input.checkValidity()) {
                showFieldError(input.name || input.closest('[data-enquiry-field]')?.dataset.enquiryField, input.validationMessage);
                firstInvalid ||= visibleControl(input);
            }
        });
        if (firstInvalid) { firstInvalid.focus(); return; }
        button.disabled = true;
        spinner.hidden = false;
        form.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(form.action, {
                method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form),
            });
            const body = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
            if (response.status === 422 && body?.errors) {
                Object.entries(body.errors).forEach(([name, errors]) => showFieldError(name, errors[0]));
                const name = Object.keys(body.errors)[0];
                const input = field(name);
                const control = input?.length && !input.tagName ? input[0] : input;
                if (control) visibleControl(control).focus();
                errorSummary.textContent = 'Please review the highlighted fields.';
                errorSummary.hidden = false;
                return;
            }
            if (!response.ok || !body?.reference) throw new Error('enquiry-failed');
            form.querySelector('[data-enquiry-fields]').hidden = true;
            form.querySelector('[data-enquiry-success-message]').textContent = body.message;
            form.querySelector('[data-enquiry-reference]').textContent = body.reference;
            const success = form.querySelector('[data-enquiry-success]');
            success.hidden = false;
            success.focus();
        } catch {
            errorSummary.textContent = 'Your request could not be confirmed. Please try again shortly or contact Karossy directly.';
            errorSummary.hidden = false;
            errorSummary.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } finally {
            button.disabled = false;
            spinner.hidden = true;
            form.setAttribute('aria-busy', 'false');
        }
    });
});
