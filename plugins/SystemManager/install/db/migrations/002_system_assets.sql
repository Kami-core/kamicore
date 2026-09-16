INSERT INTO global_settings(varname, value) VALUES
    ('system_css', '["/assets/css/system.css","/assets/vendor/tom-select/tom-select.css","/assets/admin/css/admin.css"]'),
    ('system_js', '["/assets/js/icons.js","/assets/js/common.js","/assets/vendor/tom-select/tom-select.complete.js","/assets/admin/js/common.js","/assets/js/tom-select-init.js"]'),
    ('system_custom_code', '')
ON CONFLICT (varname) DO NOTHING;
