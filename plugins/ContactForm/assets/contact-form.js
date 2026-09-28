(function () {
    'use strict';

    window.contactFormResponse = function (html, form) {
        if (!(form instanceof HTMLFormElement)) return;

        const targetSelector = form.dataset.target;
        const target = targetSelector ? document.querySelector(targetSelector) : null;
        if (!target) return;

        target.innerHTML = html;

        if (target.querySelector('[data-contact-complete]')) {
            form.hidden = true;
        }
    };
})();
