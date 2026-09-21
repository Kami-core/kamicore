/* Native controls, search and template assistance for the SEO manager. */
(() => {
  const init = () => document.querySelectorAll('[data-seo-manager]').forEach(root => {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const form = root.querySelector('[data-seo-form]');
    let dirty = false;
    let lastField = null;
    form?.addEventListener('input', () => { dirty = true; });
    form?.addEventListener('change', () => { dirty = true; });
    root.addEventListener('focusin', event => {
      if (event.target.matches('[data-seo-insert-target]')) lastField = event.target;
    });
    form?.addEventListener('submit', event => {
      const message = event.submitter?.dataset.seoConfirm;
      if (message && !window.confirm(message)) { event.preventDefault(); return; }
      dirty = false;
    });
    window.addEventListener('beforeunload', event => {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
    const records = [...root.querySelectorAll('[data-seo-record]')];
    const search = root.querySelector('[data-seo-search]');
    const filter = root.querySelector('[data-seo-filter]');
    const applyFilter = () => {
      const query = (search?.value || '').trim().toLocaleLowerCase();
      let count = 0;
      records.forEach(record => {
        const show = record.textContent.toLocaleLowerCase().includes(query) &&
          (!filter?.value || record.dataset.status === filter.value);
        record.hidden = !show;
        if (show) count++;
      });
      const counter = root.querySelector('[data-seo-count]');
      if (counter) counter.textContent = String(count);
      const empty = root.querySelector('[data-seo-empty]');
      if (empty) empty.hidden = count !== 0;
    };
    search?.addEventListener('input', applyFilter);
    filter?.addEventListener('change', applyFilter);
    root.addEventListener('click', event => {
      const language = event.target.closest('[data-seo-language]');
      if (language) {
        root.querySelectorAll('[data-seo-language]').forEach(button =>
          button.setAttribute('aria-selected', String(button === language)));
        root.querySelectorAll('[data-seo-language-panel]').forEach(panel =>
          panel.hidden = panel.dataset.seoLanguagePanel !== language.dataset.seoLanguage);
      }
      const token = event.target.closest('[data-seo-token]');
      if (token) {
        if (!lastField || lastField.closest('[hidden]')) {
          lastField = [...root.querySelectorAll('[data-seo-insert-target]')].find(field => !field.closest('[hidden]'));
        }
        if (!lastField) return;
        lastField.setRangeText(token.dataset.seoToken, lastField.selectionStart, lastField.selectionEnd, 'end');
        lastField.focus();
        lastField.dispatchEvent(new Event('input', {bubbles:true}));
      }
    });
    const json = root.querySelector('[data-seo-json]');
    const status = root.querySelector('[data-seo-json-status]');
    const checkJson = () => {
      if (!json || !status) return;
      try {
        JSON.parse(json.value);
        status.textContent = json.dataset.valid;
        status.classList.remove('seo-json-invalid');
      } catch (error) {
        status.textContent = json.dataset.invalid + error.message;
        status.classList.add('seo-json-invalid');
      }
    };
    json?.addEventListener('input', checkJson);
    checkJson();
    const sitemapDomain = root.querySelector('[data-seo-sitemap-domain]');
    sitemapDomain?.addEventListener('change', () => {
      const url = sitemapDomain.selectedOptions[0]?.dataset.url;
      if (url) window.location.href = url;
    });
    root.querySelector('.seo-records .is-active')?.scrollIntoView({block:'nearest'});
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
