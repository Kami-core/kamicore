<!-- kami:template form -->
<form action="{{action}}" method="{{method}}"{{form_attributes}}{{ajax_attributes}}>
{{fields}}
<div class="form-actions admin-form-actions">
    <button class="admin-button admin-button-primary" type="submit">{{submit_label}}</button>
</div>
</form>
<!-- /kami:template -->

<!-- kami:template field-string -->
<div class="form-field">
    <label for="{{id}}">{{label}}</label>
    <input type="{{input_type}}" id="{{id}}" name="{{name}}" value="{{value}}" placeholder="{{placeholder}}"{{field_attributes}}>
</div>
<!-- /kami:template -->

<!-- kami:template field-temporal -->
<div class="form-field">
    <label for="{{id}}">{{label}}</label>
    <input type="{{input_type}}"
           id="{{id}}"
           name="{{name}}"
           value="{{value}}"
           placeholder="{{placeholder}}"{{field_attributes}}>
    <p class="form-hint">{{description}}</p>
</div>
<!-- /kami:template -->

<!-- kami:template field-textarea -->
<div class="form-field">
    <label for="{{id}}">{{label}}</label>
    <textarea id="{{id}}" name="{{name}}" placeholder="{{placeholder}}"{{field_attributes}}>{{value}}</textarea>
</div>
<!-- /kami:template -->
<!-- kami:template field-richtext -->
{{richtext_assets}}
<div class="form-field form-field-richtext"
     data-richtext
     data-output-id="{{id}}-output"
     data-html-id="{{id}}-html"
     data-editor-id="{{id}}-editor">
    <label for="{{id}}-editor">{{label}}</label>

    <div class="richtext-modes">
        <button type="button" data-richtext-mode="visual" aria-pressed="true">Visual</button>
        <button type="button" data-richtext-mode="html" aria-pressed="false">HTML</button>
    </div>

    <div data-richtext-panel="visual">
        <div id="{{id}}-editor" class="richtext-editor"></div>
    </div>

    <div data-richtext-panel="html" hidden>
        <textarea id="{{id}}-html" rows="12" spellcheck="false">{{value}}</textarea>
        <p class="form-hint">HTML may be normalized after switching back to Visual mode.</p>
    </div>

    <input type="hidden"
           id="{{id}}-output"
           name="{{name}}"
           value="{{value}}"{{field_attributes}}>
    <p class="form-hint">{{description}}</p>
</div>
<!-- /kami:template -->


<!-- kami:template field-checkbox -->
<div class="form-field">
    <label>
        <input type="hidden" name="{{name}}" value="0">
        <input type="checkbox" id="{{id}}" name="{{name}}" value="{{checkbox_value}}"{{field_attributes}}>
        {{label}}
    </label>
</div>
<!-- /kami:template -->

<!-- kami:template field-select -->
<div class="form-field">
    <label for="{{id}}">{{label}}</label>
    {{multiple_empty}}
    <select id="{{id}}" name="{{name}}"{{field_attributes}}>
{{options}}
    </select>
    <p class="form-hint">{{description}}</p>
</div>
<!-- /kami:template -->
<!-- kami:template control-select -->
<select id="{{id}}" name="{{name}}"{{field_attributes}}>
{{options}}
</select>
<!-- /kami:template -->

<!-- kami:template field-tomselect -->
<div class="form-field">
    <label for="{{id}}">{{label}}</label>
    {{multiple_empty}}
    <select id="{{id}}"
            name="{{name}}"
            data-ajax-url="{{ajax_url}}"
            data-placeholder="{{placeholder}}"
            data-create="{{tomselect_create}}"{{field_attributes}}>
{{options}}
    </select>
    <p class="form-hint">{{description}}</p>
</div>
<!-- /kami:template -->


<!-- kami:template field-autocomplete -->
<div class="form-field form-field-autocomplete">
    <label for="{{id}}">{{label}}</label>
    {{multiple_empty}}
    <select id="{{id}}"
            name="{{name}}"
            data-ajax-url="{{ajax_url}}"
            data-placeholder="{{placeholder}}"
            data-create="1"{{field_attributes}}>
{{options}}
    </select>
    <p class="form-hint">{{description}}</p>
</div>
<!-- /kami:template -->

<!-- kami:template field-compound -->
{{field_assets}}
<div class="form-field form-field-compound"{{compound_attributes}}>
    <label>{{label}}</label>
    <div class="form-compound-list" data-repeatable-list>
{{compound_rows}}
    </div>
    <template data-repeatable-template>{{compound_template}}</template>
    <div class="form-repeatable-footer">{{compound_add_button}}</div>
    <p class="form-hint">{{description}}</p>
</div>
<!-- /kami:template -->

<!-- kami:template field-repeatable -->
{{field_assets}}
<div class="form-field form-field-repeatable"
     data-repeatable
     data-repeatable-required="{{repeatable_required}}">
    <label>{{label}}</label>
    {{multiple_empty}}
    <div class="form-repeatable-list" data-repeatable-list>
{{repeatable_rows}}
    </div>
    <template data-repeatable-template>{{repeatable_template}}</template>
    <div class="form-repeatable-footer">{{repeatable_add_button}}</div>
    <p class="form-hint">{{description}}</p>
</div>
<!-- /kami:template -->

<!-- kami:template field-media -->
{{field_assets}}
{{media_assets}}
<div class="form-field form-field-media"
     data-media-field
     data-media-multiple="{{media_multiple}}"
     data-media-root="{{media_root}}"
     data-media-accept="{{media_accept_json}}"
     data-media-can-manage="{{media_can_manage}}"
     data-repeatable-required="{{repeatable_required}}">
    <label>{{label}}</label>
    {{multiple_empty}}
    <div class="form-repeatable-list" data-media-list>
{{media_rows}}
    </div>
    <template data-media-row-template>{{media_row_template}}</template>
    <div class="form-repeatable-footer">{{media_footer}}</div>
    <p class="form-hint">{{description}}</p>
</div>
<!-- /kami:template -->
