# Third-Party Software and Assets

KamiCore includes or uses the third-party components listed below. Each component remains subject to its own copyright and license terms.

## Bundled components

### PHPMailer 7.1.1

- Purpose: email creation and SMTP transport.
- Location: `third-party/PHPMailer/`
- Project: https://github.com/PHPMailer/PHPMailer
- License: GNU Lesser General Public License 2.1 only (`LGPL-2.1-only`).
- License text: `third-party/PHPMailer/LICENSE`

Copyright and attribution notices supplied by the PHPMailer project are preserved in the bundled distribution.

### Parsedown 1.8.0

- Purpose: Markdown parsing and HTML rendering.
- Location: `third-party/parsedown/`
- Project: https://github.com/erusev/parsedown
- License: MIT License (`MIT`).
- License text: `third-party/parsedown/LICENSE.txt`

Copyright and attribution notices supplied by the Parsedown project are preserved in the bundled distribution.

### Quill 1.3.7

- Purpose: rich-text editing.
- Location: `third-party/frontend/quill/`
- Project: https://github.com/slab/quill
- License: BSD 3-Clause License (`BSD-3-Clause`).

Copyright (c) 2014, Jason Chen  
Copyright (c) 2013, salesforce.com

The bundled Quill distribution also contains a modified portion of Google's Diff Match and Patch library:

- Copyright 2006 Google Inc.
- License: Apache License 2.0 (`Apache-2.0`).

The original notice is preserved in the bundled Quill JavaScript files.

### Tom Select 2.4.3

- Purpose: enhanced select fields, autocomplete, and remote option loading.
- Location: `assets/vendor/tom-select/`
- Project: https://github.com/orchidjs/tom-select
- License: Apache License 2.0 (`Apache-2.0`).

The distributed `tom-select.complete.js` build contains bundled upstream components and retains their notices, including:

- MicroPlugin — Copyright (c) 2013 Brian Reavis & contributors — Apache License 2.0.
- highlight v3 — Johann Burkard — MIT License.

### Lucide Icons

- Purpose: UI icons used by KamiCore and compiled into the local SVG sprite.
- Locations: `assets/icons/src/`, `assets/icons/sprite.svg`
- Project: https://lucide.dev/
- License: ISC License; portions derived from Feather Icons remain under the MIT License.

Only the icons used by KamiCore are bundled rather than the complete Lucide package.

### flag-icons

- Purpose: language/country flag SVGs used by the LangSwitcher plugin.
- Location: `plugins/LangSwitcher/flags/`
- Project: https://github.com/lipis/flag-icons
- License: MIT License.

KamiCore currently bundles only the selected SVG flags required by the default language-switcher data.

## External resources

### Commissioner

- Purpose: default sans-serif typeface.
- Project: https://github.com/kosbarts/Commissioner
- Delivery: loaded at runtime through Google Fonts by the default theme.
- License: SIL Open Font License 1.1 (`OFL-1.1`).

The Commissioner font files are not distributed in the KamiCore repository.

## License notes

The presence of a third-party component in the KamiCore distribution does not change the license of that component. Copyright notices and license terms supplied with bundled third-party code must be retained when redistributing it.

The Apache License 2.0 text used by KamiCore also applies to third-party components listed above as `Apache-2.0`. Other licenses remain separate and continue to apply to their respective components.
