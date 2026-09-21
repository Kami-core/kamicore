UPDATE plugins
SET settings =
        (COALESCE(settings, '{}'::jsonb) - 'profile_page_enabled'::text)
        || jsonb_build_object(
            'profile_page_enabled',
            CASE
                WHEN settings ? 'profile_page_enabled'
                     AND jsonb_typeof(settings->'profile_page_enabled') <> 'object'
                    THEN settings->'profile_page_enabled'
                WHEN COALESCE(default_settings, '{}'::jsonb) ? 'profile_page_enabled'
                    THEN default_settings->'profile_page_enabled'
                ELSE '1'::jsonb
            END
        ),
    default_settings = '{}'::jsonb,
    global_settings = '{}'::jsonb
WHERE system_name = 'UserProfile';
