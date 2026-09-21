(function () {
    'use strict';

    const BATCH_SIZE = 10;

    function init() {
        document.querySelectorAll('[data-tm-batch]:not([data-tm-batch-ready])')
            .forEach(initPanel);
    }

    function initPanel(panel) {
        panel.dataset.tmBatchReady = '1';

        const startButton = panel.querySelector('[data-tm-batch-start]');
        const stopButton = panel.querySelector('[data-tm-batch-stop]');
        const status = panel.querySelector('[data-tm-batch-status]');
        const state = panel.querySelector('[data-tm-batch-state]');
        const translatedOutput = panel.querySelector('[data-tm-batch-translated]');
        const skippedOutput = panel.querySelector('[data-tm-batch-skipped]');
        const errorsOutput = panel.querySelector('[data-tm-batch-errors]');

        if (!startButton || !stopButton || !status || !state) return;

        let stopRequested = false;
        let running = false;

        stopButton.addEventListener('click', function () {
            if (!running) return;
            stopRequested = true;
            stopButton.disabled = true;
            state.textContent = panel.dataset.stoppingLabel || 'Stopping...';
        });

        startButton.addEventListener('click', async function () {
            if (running) return;

            const source = panel.dataset.source || '';
            const target = panel.querySelector('[name="trm-batch-target"]')?.value || '';
            const provider = panel.querySelector('[name="trm-batch-provider"]')?.value || '';
            const context = panel.querySelector('[name="trm-batch-context"]')?.value || '';
            const instructions = panel.querySelector('[name="trm-batch-instructions"]')?.value || '';
            const scope = panel.querySelector('[name="tm-batch-scope"]:checked')?.value || 'all';
            const kind = panel.dataset.kind || '';

            if (!source || !target || source === target) {
                showState(panel.dataset.languagesDifferLabel || 'Source and target languages must be different.');
                return;
            }

            const allIds = Array.from(document.querySelectorAll('[data-tm-batch-item]'))
                .map(input => input.value)
                .filter(Boolean);
            const selectedIds = Array.from(document.querySelectorAll('[data-tm-batch-item]:checked'))
                .map(input => input.value)
                .filter(Boolean);

            let remainingIds = [];
            const explicitIds = scope !== 'all' || kind === 'system';

            if (explicitIds) {
                remainingIds = scope === 'selected' ? selectedIds : allIds;
                if (remainingIds.length === 0) {
                    showState(panel.dataset.selectItemsLabel || 'Select at least one item.');
                    return;
                }
            }

            let totals = {translated: 0, skipped: 0, errors: 0};
            let cursor = '0';
            stopRequested = false;
            running = true;
            setRunning(true);
            updateCounters(totals);
            showState(panel.dataset.runningLabel || 'Translating...');

            try {
                while (!stopRequested) {
                    const payload = {
                        kind,
                        scope,
                        source,
                        target,
                        provider,
                        context,
                        instructions,
                        cursor,
                        type_id: panel.dataset.typeId || 0,
                        entity_type: panel.dataset.entityType || ''
                    };

                    if (explicitIds) {
                        payload.ids = remainingIds.splice(0, BATCH_SIZE);
                        if (payload.ids.length === 0) break;
                    }

                    const response = await fetch(panel.dataset.endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();
                    if (!response.ok || result.status !== 'ok') {
                        totals.errors++;
                        updateCounters(totals);
                        throw new Error(result.message || `HTTP ${response.status}`);
                    }

                    totals.translated += Number(result.translated || 0);
                    totals.skipped += Number(result.skipped || 0);
                    totals.errors += Number(result.errors || 0);
                    cursor = String(result.cursor || cursor);
                    updateCounters(totals);

                    if (!explicitIds && result.done) break;
                    if (explicitIds && remainingIds.length === 0) break;
                }

                showState(stopRequested
                    ? (panel.dataset.stoppedLabel || 'Stopped.')
                    : (panel.dataset.doneLabel || 'Done.'));
            } catch (error) {
                console.error('Translation batch failed:', error);
                showState(error instanceof Error ? error.message : String(error));
            } finally {
                running = false;
                setRunning(false);
            }
        });

        function updateCounters(totals) {
            if (translatedOutput) translatedOutput.textContent = String(totals.translated);
            if (skippedOutput) skippedOutput.textContent = String(totals.skipped);
            if (errorsOutput) errorsOutput.textContent = String(totals.errors);
        }

        function showState(message) {
            status.hidden = false;
            state.textContent = message;
        }

        function setRunning(isRunning) {
            startButton.hidden = isRunning;
            stopButton.hidden = !isRunning;
            stopButton.disabled = false;

            panel.querySelectorAll('select, textarea, input[type="radio"], [data-tm-batch-item]')
                .forEach(control => {
                    control.disabled = isRunning;
                });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})();
