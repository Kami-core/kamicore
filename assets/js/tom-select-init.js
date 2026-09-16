/* Tom Select initializer for Kami forms.
 * Requires Tom Select (https://tom-select.js.org/) to be loaded.
 */
(function () {
  'use strict';


function encodePathSegment(value) {
  return encodeURIComponent(String(value ?? ''));
}


function buildEndpoint(base, pairs) {
  let url = base.replace(/\/+$/, '');
  for (const [k, v] of Object.entries(pairs)) {
    if (v === undefined || v === null) continue;
    if (v === '' && k !== 'q') continue; // allow empty q only
    url += '/' + encodePathSegment(k) + '/' + encodePathSegment(v);
  }
  return url;
}

function collectDataParams(el, overrides = {}) {
  // Data keys to ignore (UI-only / internal)
  const ignore = new Set([
    'remoteSelect',   // data-remote-select
    'placeholder',    // data-placeholder
    'prefill',        // data-prefill
    'allowEmpty',     // data-allow-empty
  ]);

  // Read ALL dataset keys (data-*)
  // dataset turns data-foo-bar into el.dataset.fooBar
  const params = {};

  for (const [key, value] of Object.entries(el.dataset)) {
    if (ignore.has(key)) continue;

    if (key === 'endpoint') continue; // base URL, not a param
    if (value === undefined || value === null) continue;

    // Special case: data-param-domain_id => dataset.paramDomain_id
    // Because underscore isn't camelized, it stays in key; we handle both styles.
    if (key.startsWith('param')) {
      // data-param-domain_id="1" => key "paramDomain_id" or "paramDomainId" depending on author
      // We want: domain_id
      let raw = key.substring(4); // after 'param'
      if (raw.startsWith('_')) raw = raw.substring(1);

      // If author wrote data-param-domain-id, dataset becomes paramDomainId (camelCase)
      // So we convert camelCase to snake_case for safety.
      const snake = raw
        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
        .toLowerCase();

      params[snake] = value;
      continue;
    }

    // Normal dataset key: camelCase => snake_case
    const snake = key
      .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
      .toLowerCase();

    params[snake] = value;
  }

  // Apply overrides last (highest priority)
  for (const [k, v] of Object.entries(overrides)) {
    params[k] = v;
  }

  return params;
}

async function fetchOptions(el, query, page) {
  const base = el.dataset.endpoint || '/ajax/Form/get_select_options';

  const params = collectDataParams(el, {
    q: query ?? '',
    page: page ?? 1,
  });

  const url = buildEndpoint(base, params);

  const res = await fetch(url, {
    method: 'GET',
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
  });

  if (!res.ok) {
    throw new Error('HTTP ' + res.status);
  }

  const json = await res.json();

  if (!json || json.status !== 'ok' || !json.data || !Array.isArray(json.data.items)) {
    throw new Error('Invalid response format');
  }

  return json.data;
}


  function mapItem(item) {
    // Tom Select expects: { value, text } by default, but we configure valueField/labelField.
    // Keep the payload as-is; only ensure required keys exist.
    return {
      id: String(item.id ?? ''),
      title: String(item.title ?? ''),
      subtitle: item.subtitle ?? null,
      disabled: !!item.disabled,
      meta: item.meta ?? null,
    };
  }

  function initOne(el) {
    const placeholder = el.getAttribute('data-placeholder') || 'Search...';
    const allowEmpty = el.getAttribute('data-allow-empty') === '1';
    const prefill = el.getAttribute('data-prefill') === '1'; // if you want to prefill via server later

	const isMultiple = el.multiple;

	//console.log(el);

    const ts = new TomSelect(el, {
      plugins: isMultiple ? ['remove_button'] : [],
		maxItems: isMultiple ? null : 1,      // multiple: unlimited, single: one
		closeAfterSelect: !isMultiple,        // multiple: keep dropdown open
		hideSelected: isMultiple,             // useful for multiple lists
      create: false,
      preload: false,
      placeholder,

      valueField: 'id',
      labelField: 'title',
      searchField: ['title', 'subtitle'],

      render: {
        option: function (data, escape) {
          const title = escape(data.title || '');
          const subtitle = data.subtitle ? `<div class="uk-text-meta">${escape(data.subtitle)}</div>` : '';
          return `<div>${title}${subtitle}</div>`;
        },
        item: function (data, escape) {
          return `<div>${escape(data.title || '')}</div>`;
        }
      },

      loadThrottle: 250,
      shouldLoad: function (query) {
        // Load also on empty query? Usually no. Keep it conservative.
        return query.length >= 1;
      },

      load: async function (query, callback) {
        try {
          const data = await fetchOptions(el, query, 1);
          callback(data.items.map(mapItem));
        } catch (e) {
          callback();
        }
      },

      onInitialize: function () {
        // If the select already has <option selected>, Tom Select will pick them up automatically.
        // That's the recommended "edit form" path (no extra AJAX needed).
        // If you later want true AJAX prefill: implement /ids/.. mode server-side and call it here.
        if (prefill) {
          // placeholder for future enhancement
        }
      }
    });

    // Optional: allow empty value for single selects (clear button-like behavior)
    if (!el.multiple && allowEmpty) {
      // Add a clear button using UIKit (simple approach)
      const wrapper = el.closest('.uk-form-controls');
      if (wrapper) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'uk-button uk-button-default uk-button-small uk-margin-small-top';
        btn.textContent = 'Clear';
        btn.addEventListener('click', () => ts.clear(true));
        wrapper.appendChild(btn);
      }
    }

    // Save instance for debugging
    el._tomselect = ts;
  }

  function elementsWithin(root, selector) {
    const elements = [];
    if (root instanceof Element && root.matches(selector)) {
      elements.push(root);
    }
    if (root && typeof root.querySelectorAll === "function") {
      elements.push(...root.querySelectorAll(selector));
    }
    return elements;
  }

  function initFormsTomSelect(el) {
    if (el.tomselect || el._tomselect || typeof TomSelect === "undefined") return;

    const endpoint = el.dataset.ajaxUrl || "";
    const isMultiple = el.multiple;
    const ts = new TomSelect(el, {
      plugins: isMultiple ? ["remove_button"] : [],
      valueField: "value",
      labelField: "label",
      searchField: ["label", "subtitle"],
      maxItems: isMultiple ? null : 1,
      closeAfterSelect: !isMultiple,
      hideSelected: isMultiple,
      placeholder: el.dataset.placeholder || "",
      create: el.dataset.create === "1",
      createOnBlur: el.dataset.create === "1",
      persist: false,
      preload: endpoint ? "focus" : false,
      loadThrottle: 250,
      render: {
        option(data, escape) {
          const subtitle = data.subtitle
            ? "<small class=\"form-option-subtitle\">" + escape(data.subtitle) + "</small>"
            : "";
          return "<div class=\"form-option\"><span>" + escape(data.label || "") + "</span>" + subtitle + "</div>";
        },
        item(data, escape) {
          return "<div>" + escape(data.label || "") + "</div>";
        }
      },
      load: endpoint ? function (query, callback) {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set("q", query);

        fetch(url, {
          credentials: "same-origin",
          headers: {
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest"
          }
        })
          .then(response => {
            if (!response.ok) throw new Error("HTTP " + response.status);
            return response.json();
          })
          .then(data => {
            const items = Array.isArray(data)
              ? data
              : (Array.isArray(data.items) ? data.items : []);
            callback(items);
          })
          .catch(() => callback());
      } : undefined
    });

    el._tomselect = ts;
  }

  function initRemoteSelects(root = document) {
    elementsWithin(root, "select[data-remote-select=\"1\"]").forEach((el) => {
      if (el.tomselect || el._tomselect) return;
      initOne(el);
    });
  }

  function initFormsTomSelects(root = document) {
    elementsWithin(root, "select[data-ajax-url]").forEach(initFormsTomSelect);
  }

  function initAll(root = document) {
    initRemoteSelects(root);
    initFormsTomSelects(root);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initAll(document));
  } else {
    initAll(document);
  }

  window.KamiTomSelect = {
    init: initAll,
    initRemoteSelects,
    initFormsTomSelects
  };
  window.Kami?.registerInitializer?.(initAll);
})();

