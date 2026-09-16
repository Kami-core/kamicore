INSERT INTO global_settings(varname, value) VALUES
    ('system_css', '["/assets/css/system.css","/assets/vendor/tom-select/tom-select.css","/assets/css/admin.css"]'),
    ('system_custom_code', '')
ON CONFLICT (varname) DO NOTHING;
