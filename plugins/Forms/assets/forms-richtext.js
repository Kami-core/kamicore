'use strict';

/* global Quill */

(function () {
    let extendedFormatsRegistered = false;

    function registerExtendedFormats() {
        if (extendedFormatsRegistered || typeof Quill === 'undefined') {
            return;
        }

        const BlockEmbed = Quill.import('blots/block/embed');

        class DividerBlot extends BlockEmbed {}

        DividerBlot.blotName = 'divider';
        DividerBlot.tagName = 'hr';
        Quill.register(DividerBlot);

        const icons = Quill.import('ui/icons');
        icons.divider = '<svg viewBox="0 0 18 18"><line class="ql-stroke" x1="3" x2="15" y1="9" y2="9"></line></svg>';

        extendedFormatsRegistered = true;
    }

    function insertImageUrl(quill, url) {
        const range = quill.getSelection(true)
            || { index: quill.getLength() };
        quill.insertEmbed(range.index, 'image', url, Quill.sources.USER);
        quill.setSelection(range.index + 1, Quill.sources.SILENT);
    }

    function insertImageFromUrl() {
        const url = window.prompt('Image URL');
        if (!url) {
            return;
        }

        insertImageUrl(this.quill, url);
    }

    async function insertImage() {
        if (typeof window.KamiMedia?.open === 'function') {
            try {
                const file = await window.KamiMedia.open({
                    accept: ['image/*'],
                    title: 'Select image'
                });

                if (!file) {
                    return;
                }
                if (file.url) {
                    insertImageUrl(this.quill, file.url);
                }
                return;
            } catch (error) {
                console.error('Media picker failed.', error);
            }
        }

        insertImageFromUrl.call(this);
    }

    function insertDivider() {
        const range = this.quill.getSelection(true)
            || { index: this.quill.getLength() };
        this.quill.insertEmbed(range.index, 'divider', true, Quill.sources.USER);
        this.quill.setSelection(range.index + 1, Quill.sources.SILENT);
    }

    function setMode(root, quill, htmlElement, outputElement, mode) {
        const visualPanel = root.querySelector('[data-richtext-panel="visual"]');
        const htmlPanel = root.querySelector('[data-richtext-panel="html"]');
        const visualButton = root.querySelector('[data-richtext-mode="visual"]');
        const htmlButton = root.querySelector('[data-richtext-mode="html"]');

        if (!visualPanel || !htmlPanel || !visualButton || !htmlButton) {
            return;
        }

        if (mode === 'html') {
            htmlElement.value = quill.root.innerHTML || '';
            visualPanel.hidden = true;
            htmlPanel.hidden = false;
        } else {
            const html = htmlElement.value || '';
            quill.setContents([]);
            if (html.trim()) {
                quill.clipboard.dangerouslyPasteHTML(html);
            }
            outputElement.value = quill.root.innerHTML || '';
            visualPanel.hidden = false;
            htmlPanel.hidden = true;
        }

        root.dataset.richtextActiveMode = mode;
        visualButton.setAttribute('aria-pressed', mode === 'visual' ? 'true' : 'false');
        htmlButton.setAttribute('aria-pressed', mode === 'html' ? 'true' : 'false');
    }

    function initField(root) {
        if (root.dataset.richtextInitialized === '1') {
            return;
        }

        const outputElement = document.getElementById(root.dataset.outputId || '');
        const htmlElement = document.getElementById(root.dataset.htmlId || '');
        const editorElement = document.getElementById(root.dataset.editorId || '');
        if (!outputElement || !htmlElement || !editorElement) {
            return;
        }

        if (typeof Quill === 'undefined') {
            const visualPanel = root.querySelector('[data-richtext-panel="visual"]');
            const htmlPanel = root.querySelector('[data-richtext-panel="html"]');
            if (visualPanel) visualPanel.hidden = true;
            if (htmlPanel) htmlPanel.hidden = false;
            return;
        }

        root.dataset.richtextInitialized = '1';
        registerExtendedFormats();

        const quill = new Quill(editorElement, {
            theme: 'snow',
            modules: {
                clipboard: {
                    matchVisual: false
                },
                toolbar: {
                    container: [
                        [{ header: [2, 3, 4, 5, 6, false] }],
                        ['bold', 'italic', 'underline', 'strike', 'code'],
                        [{ script: 'sub' }, { script: 'super' }],
                        ['blockquote', 'code-block'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ indent: '-1' }, { indent: '+1' }],
                        [{ align: [] }],
                        ['link', 'image', 'video'],
                        ['divider'],
                        ['clean']
                    ],
                    handlers: {
                        image: insertImage,
                        divider: insertDivider
                    }
                }
            }
        });
        const dividerButton = root.querySelector('.ql-divider');
        if (dividerButton) {
            dividerButton.title = 'Horizontal divider';
            dividerButton.setAttribute('aria-label', 'Horizontal divider');
        }


        const initialHtml = outputElement.value || htmlElement.value || '';
        htmlElement.value = initialHtml;
        if (initialHtml.trim()) {
            quill.clipboard.dangerouslyPasteHTML(initialHtml);
        }

        quill.on('text-change', function () {
            if (root.dataset.richtextActiveMode === 'visual') {
                outputElement.value = quill.root.innerHTML || '';
            }
        });

        root.querySelectorAll('[data-richtext-mode]').forEach(function (button) {
            button.addEventListener('click', function () {
                setMode(
                    root,
                    quill,
                    htmlElement,
                    outputElement,
                    button.dataset.richtextMode === 'html' ? 'html' : 'visual'
                );
            });
        });

        const form = root.closest('form');
        form?.addEventListener('submit', function () {
            outputElement.value = root.dataset.richtextActiveMode === 'html'
                ? htmlElement.value
                : (quill.root.innerHTML || '');
        }, { capture: true });

        root.dataset.richtextActiveMode = 'visual';
    }

    function initAll(container) {
        const root = container || document;
        if (root.matches?.('[data-richtext]')) {
            initField(root);
        }
        root.querySelectorAll?.('[data-richtext]').forEach(initField);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll(document);
        });
    } else {
        initAll(document);
    }

    window.FormsRichtext = { init: initAll };

    window.Kami?.registerInitializer?.(initAll);
})();

