document.querySelectorAll('[data-gyro-gallery]').forEach(gallery => {
    const slides = [...gallery.querySelectorAll('[data-gyro-gallery-slide]')];
    const choices = [...gallery.querySelectorAll('[data-gyro-gallery-select]')];
    const status = gallery.querySelector('[data-gyro-gallery-status]');
    const playback = gallery.querySelector('[data-gyro-gallery-playback]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (!slides.length || slides.length !== choices.length || !playback) return;

    let activeIndex = 0;
    let changeRequest = 0;
    let timer = null;
    let autoplay = !reducedMotion.matches;
    let inView = !('IntersectionObserver' in window);
    let hovered = gallery.matches(':hover');
    let focused = gallery.contains(document.activeElement);

    const showSlide = async (index, manual = false) => {
        const request = ++changeRequest;
        const image = slides[index];
        // Keep the current photo visible until the incoming one is decoded.
        image.loading = 'eager';
        try { await image.decode(); } catch { return; }
        if (request !== changeRequest) return;
        activeIndex = index;
        slides.forEach((slide, position) => {
            slide.classList.toggle('is-active', position === index);
            slide.setAttribute('aria-hidden', String(position !== index));
        });
        choices.forEach((choice, position) => choice.setAttribute('aria-pressed', String(position === index)));
        if (status) {
            status.setAttribute('aria-live', manual ? 'polite' : 'off');
            status.textContent = `Photo ${index + 1} of ${slides.length}: ${choices[index].dataset.gyroLabel}`;
        }
    };

    const syncPlayback = () => {
        window.clearInterval(timer);
        timer = null;
        playback.setAttribute('aria-pressed', String(!autoplay));
        playback.setAttribute('aria-label', `${autoplay ? 'Pause' : 'Play'} Gyro Air slideshow`);
        playback.querySelector('[data-gyro-gallery-playback-label]').textContent = `${autoplay ? 'Pause' : 'Play'} slideshow`;
        playback.querySelector('i').className = `bi bi-${autoplay ? 'pause' : 'play'}-fill`;
        if (!autoplay || !inView || hovered || focused || document.hidden || slides.length < 2) return;
        timer = window.setInterval(() => showSlide((activeIndex + 1) % slides.length), 4500);
    };

    choices.forEach((choice, index) => choice.addEventListener('click', () => {
        showSlide(index, true);
        syncPlayback();
    }));
    playback.addEventListener('click', () => { autoplay = !autoplay; syncPlayback(); });
    gallery.addEventListener('mouseenter', () => { hovered = true; syncPlayback(); });
    gallery.addEventListener('mouseleave', () => { hovered = false; syncPlayback(); });
    gallery.addEventListener('focusin', () => { focused = true; syncPlayback(); });
    gallery.addEventListener('focusout', event => {
        focused = gallery.contains(event.relatedTarget);
        syncPlayback();
    });
    document.addEventListener('visibilitychange', syncPlayback);
    reducedMotion.addEventListener('change', event => {
        if (event.matches) autoplay = false;
        syncPlayback();
    });
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            inView = entry.isIntersecting && entry.intersectionRatio >= .2;
            syncPlayback();
        }, { threshold: .2 }).observe(gallery);
    }
    syncPlayback();
});
