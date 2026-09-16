/* global UIkit, Quill */

/**
 * Initialize all quill_raw fields on the page.
 * - Stores only HTML
 * - On submit: saves HTML from active tab
 */

(function () {
  function initField(root) {
    const outId = root.dataset.targetInput;
    const htmlId = root.dataset.targetHtml;
    const editorId = root.dataset.targetEditor;

    const outEl = document.getElementById(outId);
    const htmlEl = document.getElementById(htmlId);
    const editorEl = document.getElementById(editorId);

    if (!outEl || !htmlEl || !editorEl) return;
    if (root.__qr_inited) return;
    root.__qr_inited = true;

    const minH = parseInt(root.dataset.minHeight || '200', 10);
    editorEl.style.minHeight = `${minH}px`;

    const quill = new Quill(editorEl, {
      theme: 'snow',
      modules: {
        toolbar: {
          container: [
            ['bold', 'italic', 'underline'],
            [{ header: [1, 2, false] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link', 'image'],
            ['clean']
          ],
          handlers: {
            image: function () {
              // Placeholder: you will replace this with MediaManager modal
              const url = prompt('Image URL');
              if (!url) return;

              const range = quill.getSelection(true) || { index: quill.getLength() };
              quill.insertEmbed(range.index, 'image', url, Quill.sources.USER);
              quill.setSelection(range.index + 1, Quill.sources.SILENT);
            }
          }
        }
      }
    });

    // Initial load: HTML is canonical, but we show Visual by default.
    const initialHtml = htmlEl.value || outEl.value || '';
    if (initialHtml.trim()) {
      quill.clipboard.dangerouslyPasteHTML(initialHtml);
    }

    let activeTab = 'visual';

    // Sync on tab switch (UIKit switcher emits show)
    const switcher = root.querySelector('.uk-switcher');
    if (switcher) {
      UIkit.util.on(switcher, 'show', (e) => {
        const items = Array.from(e.target.parentNode.children);
        const idx = items.indexOf(e.target);

        if (idx === 0) {
          // HTML -> Visual: import html to quill (normalization can happen)
          activeTab = 'visual';
          quill.setContents([]);
          quill.clipboard.dangerouslyPasteHTML(htmlEl.value || '');
        } else {
          // Visual -> HTML: export current quill html to textarea
          activeTab = 'html';
          htmlEl.value = quill.root.innerHTML || '';
        }
      });
    }

    // On form submit: choose source based on active tab (your rule)
    const form = root.closest('form');
    if (form) {
      form.addEventListener('submit', () => {
        if (activeTab === 'html') {
          // Save raw HTML as is; do not import into Quill
          outEl.value = htmlEl.value || '';
        } else {
          // Save current visual html
          outEl.value = quill.root.innerHTML || '';
        }
      }, { capture: true });
    }
  }

  function initAll() {
    document.querySelectorAll('[data-quill-raw]').forEach(initField);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  // Optional: if you dynamically inject forms, call window.initQuillRawFields(container)
  window.initQuillRawFields = function (container) {
    (container || document).querySelectorAll?.('[data-quill-raw]')?.forEach(initField);
  };
})();

