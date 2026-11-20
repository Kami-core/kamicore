(function () {
    'use strict';

    let overlay = null;
    let content = null;
    let activeMedia = null;
    let previousFocus = null;
    let prevButton = null;
    let nextButton = null;
    let items = [];
    let currentIndex = 0;
    let touchStartX = 0;
    let touchStartY = 0;
    let touchActive = false;
    let previousOverflow = '';

    function icon(name) {
        return `<svg class="icon icon-${name}" aria-hidden="true"></svg>`;
    }

    function createOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'litebox-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Media preview');

        overlay.innerHTML = `
            <button class="litebox-close" type="button" aria-label="Close">${icon('x')}</button>
            <button class="litebox-prev" type="button" aria-label="Previous image">${icon('chevron-left')}</button>
            <div class="litebox-content">

            </div>
            <button class="litebox-next" type="button" aria-label="Next image">${icon('chevron-right')}</button>
        `;

        document.body.appendChild(overlay);

        content = overlay.querySelector('.litebox-content');
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
            touchActive = activeMedia?.tagName === 'IMG' && event.touches.length === 1;
            if (!touchActive) return;

            touchStartX = event.changedTouches[0].clientX;
            touchStartY = event.changedTouches[0].clientY;
        }, { passive: true });

        overlay.addEventListener('touchend', event => {
            if (!touchActive || activeMedia?.tagName !== 'IMG' || items.length < 2 || event.changedTouches.length !== 1) return;
            touchActive = false;

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

    function youtubeId(value) {
        // Accept standard URLs, Shorts, short links, embed URLs, and bare IDs.
        if (/^[A-Za-z0-9_-]{11}$/.test(value)) return value;

        try {
            const url = new URL(value, document.baseURI);
            const host = url.hostname.toLowerCase();
            const youtubeHost = host === 'youtube.com' || host.endsWith('.youtube.com') ||
                host === 'youtube-nocookie.com' || host.endsWith('.youtube-nocookie.com');

            let id = null;
            if (host === 'youtu.be' || host === 'www.youtu.be') {
                id = url.pathname.split('/')[1];
            } else if (youtubeHost) {
                const parts = url.pathname.split('/').filter(Boolean);
                if (parts[0] === 'watch') id = url.searchParams.get('v');
                if (['shorts', 'embed', 'live'].includes(parts[0])) id = parts[1];
            }

            return /^[A-Za-z0-9_-]{11}$/.test(id || '') ? id : null;
        } catch {
            return null;
        }
    }

    function mediaSource(link) {
        if (link.classList.contains('litebox-youtube')) {
            const id = youtubeId(link.getAttribute('href') || '');
            return id ? { type: 'youtube', src: `https://www.youtube-nocookie.com/embed/${id}?autoplay=1` } : null;
        }

        if (link.classList.contains('litebox-video')) {
            return { type: 'video', src: link.href };
        }

        return { type: 'image', src: link.href };
    }

    function clearMedia() {
        if (activeMedia?.tagName === 'VIDEO') {
            activeMedia.pause();
            activeMedia.removeAttribute('src');
            activeMedia.load();
        }
        if (activeMedia?.tagName === 'IFRAME') activeMedia.removeAttribute('src');
        content.replaceChildren();
        activeMedia = null;
    }

    function preload(index) {
        if (items.length < 2) return;
        const link = items[index];
        if (link.classList.contains('litebox-video') || link.classList.contains('litebox-youtube')) return;

        const preloadImage = new Image();
        preloadImage.src = link.href;
    }

    function show(index) {
        const nextIndex = (index + items.length) % items.length;
        const link = items[nextIndex];
        const source = mediaSource(link);
        if (!source) return;

        clearMedia();
        currentIndex = nextIndex;
        overlay.classList.toggle('litebox-vertical', link.classList.contains('litebox-vertical'));
        overlay.classList.toggle('litebox-has-video', source.type !== 'image');

        if (source.type === 'youtube') {
            activeMedia = document.createElement('iframe');
            activeMedia.className = 'litebox-iframe';
            activeMedia.title = 'YouTube video';
            activeMedia.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
            activeMedia.allowFullscreen = true;
        } else if (source.type === 'video') {
            activeMedia = document.createElement('video');
            activeMedia.className = 'litebox-video-player';
            activeMedia.controls = true;
            activeMedia.autoplay = true;
            activeMedia.playsInline = true;
            activeMedia.preload = 'metadata';
            const poster = link.querySelector('img')?.src;
            if (poster) activeMedia.poster = poster;
        } else {
            activeMedia = document.createElement('img');
            activeMedia.className = 'litebox-image';
            activeMedia.alt = link.querySelector('img')?.alt || '';
        }

        content.appendChild(activeMedia);
        activeMedia.src = source.src;

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

        if (!mediaSource(link)) return;
        previousFocus = document.activeElement;
        show(currentIndex);

        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        overlay.classList.add('open');
        overlay.querySelector('.litebox-close').focus();
    }

    function close() {
        if (!overlay?.classList.contains('open')) return;

        overlay.classList.remove('open');
        clearMedia();
        document.body.style.overflow = previousOverflow;
        if (previousFocus?.isConnected) previousFocus.focus();
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

        if (!mediaSource(link)) return;
        event.preventDefault();
        open(link);
    });

    document.addEventListener('keydown', event => {
        if (!overlay?.classList.contains('open')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
        if (event.key === 'ArrowLeft' && activeMedia?.tagName === 'IMG') previous();
        if (event.key === 'ArrowRight' && activeMedia?.tagName === 'IMG') next();
    });
})();
