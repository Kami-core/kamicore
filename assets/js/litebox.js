(function () {
    'use strict';

    let overlay = null;
    let image = null;
    let prevButton = null;
    let nextButton = null;
    let items = [];
    let currentIndex = 0;
    let touchStartX = 0;
    let touchStartY = 0;
    let previousOverflow = '';

    function icon(name) {
        return `<svg class="icon icon-${name}" aria-hidden="true"></svg>`;
    }

    function createOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'litebox-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Image preview');

        overlay.innerHTML = `
            <button class="litebox-close" type="button" aria-label="Close">${icon('x')}</button>
            <button class="litebox-prev" type="button" aria-label="Previous image">${icon('chevron-left')}</button>
            <div class="litebox-content">
                <img class="litebox-image" src="" alt="">
            </div>
            <button class="litebox-next" type="button" aria-label="Next image">${icon('chevron-right')}</button>
        `;

        document.body.appendChild(overlay);

        image = overlay.querySelector('.litebox-image');
        prevButton = overlay.querySelector('.litebox-prev');
        nextButton = overlay.querySelector('.litebox-next');

        overlay.querySelector('.litebox-close').addEventListener('click', close);
        prevButton.addEventListener('click', previous);
        nextButton.addEventListener('click', next);

        overlay.addEventListener('click', event => {
            if (event.target === overlay || event.target.classList.contains('litebox-content')) {
                close();
            }
        });

        overlay.addEventListener('touchstart', event => {
            if (event.changedTouches.length !== 1) return;

            touchStartX = event.changedTouches[0].clientX;
            touchStartY = event.changedTouches[0].clientY;
        }, { passive: true });

        overlay.addEventListener('touchend', event => {
            if (items.length < 2 || event.changedTouches.length !== 1) return;

            const diffX = event.changedTouches[0].clientX - touchStartX;
            const diffY = event.changedTouches[0].clientY - touchStartY;

            if (Math.abs(diffX) < 50 || Math.abs(diffX) <= Math.abs(diffY)) return;

            diffX < 0 ? next() : previous();
        }, { passive: true });
    }

    function getGallery(link) {
        const gallery = link.dataset.gallery;

        if (!gallery) return [link];

        return [...document.querySelectorAll('a.litebox[data-gallery]')]
            .filter(item => item.dataset.gallery === gallery);
    }

    function preload(index) {
        if (items.length < 2) return;

        const preloadImage = new Image();
        preloadImage.src = items[index].href;
    }

    function show(index) {
        currentIndex = (index + items.length) % items.length;

        const link = items[currentIndex];
        const thumbnail = link.querySelector('img');

        image.src = link.href;
        image.alt = thumbnail?.alt || '';

        const hasGallery = items.length > 1;
        prevButton.hidden = !hasGallery;
        nextButton.hidden = !hasGallery;

        if (hasGallery) {
            preload((currentIndex - 1 + items.length) % items.length);
            preload((currentIndex + 1) % items.length);
        }
    }

    function open(link) {
        if (!overlay) createOverlay();

        items = getGallery(link);
        currentIndex = Math.max(0, items.indexOf(link));

        show(currentIndex);

        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        overlay.classList.add('open');
        overlay.querySelector('.litebox-close').focus();
    }

    function close() {
        if (!overlay?.classList.contains('open')) return;

        overlay.classList.remove('open');
        document.body.style.overflow = previousOverflow;
    }

    function next() {
        if (items.length > 1) show(currentIndex + 1);
    }

    function previous() {
        if (items.length > 1) show(currentIndex - 1);
    }

    document.addEventListener('click', event => {
        const link = event.target.closest('a.litebox');
        if (!link) return;

        event.preventDefault();
        open(link);
    });

    document.addEventListener('keydown', event => {
        if (!overlay?.classList.contains('open')) return;

        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') previous();
        if (event.key === 'ArrowRight') next();
    });
})();
