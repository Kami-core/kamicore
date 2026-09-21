UPDATE plugins
SET settings = COALESCE(settings, '{}'::jsonb) - 'test'::text,
    default_settings = COALESCE(default_settings, '{}'::jsonb) - 'test'::text,
    global_settings = COALESCE(global_settings, '{}'::jsonb) - 'test'::text
WHERE system_name = 'PageManager';

UPDATE plugin_domains
SET local_settings = COALESCE(local_settings, '{}'::jsonb) - 'test'::text
WHERE plugin_id = (
    SELECT plugin_id
    FROM plugins
    WHERE system_name = 'PageManager'
);
