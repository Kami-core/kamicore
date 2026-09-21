UPDATE plugins
SET settings =
        ((((((COALESCE(settings, '{}'::jsonb)
            - 'sso_authentication'::text)
            - 'registration_enabled'::text)
            - 'two_factor_auth'::text)
            - 'login_action'::text)
            - 'email_activation'::text)
            - 'redirect_page'::text)
        || jsonb_build_object(
            'sso_authentication',
            CASE
                WHEN settings ? 'sso_authentication'
                     AND jsonb_typeof(settings->'sso_authentication') <> 'object'
                    THEN settings->'sso_authentication'
                WHEN COALESCE(default_settings, '{}'::jsonb) ? 'sso_authentication'
                    THEN default_settings->'sso_authentication'
                ELSE '1'::jsonb
            END,
            'registration_enabled',
            CASE
                WHEN settings ? 'registration_enabled'
                     AND jsonb_typeof(settings->'registration_enabled') <> 'object'
                    THEN settings->'registration_enabled'
                WHEN COALESCE(default_settings, '{}'::jsonb) ? 'registration_enabled'
                    THEN default_settings->'registration_enabled'
                ELSE '1'::jsonb
            END,
            'two_factor_auth',
            CASE
                WHEN settings ? 'two_factor_auth'
                     AND jsonb_typeof(settings->'two_factor_auth') <> 'object'
                    THEN settings->'two_factor_auth'
                WHEN COALESCE(global_settings, '{}'::jsonb) ? 'two_factor_auth'
                    THEN global_settings->'two_factor_auth'
                ELSE '1'::jsonb
            END,
            'login_action',
            CASE
                WHEN settings ? 'login_action'
                     AND jsonb_typeof(settings->'login_action') <> 'object'
                    THEN settings->'login_action'
                WHEN COALESCE(default_settings, '{}'::jsonb) ? 'login_action'
                    THEN default_settings->'login_action'
                ELSE '"reload"'::jsonb
            END,
            'email_activation',
            CASE
                WHEN settings ? 'email_activation'
                     AND jsonb_typeof(settings->'email_activation') <> 'object'
                    THEN settings->'email_activation'
                WHEN COALESCE(global_settings, '{}'::jsonb) ? 'email_activation'
                    THEN global_settings->'email_activation'
                ELSE '1'::jsonb
            END,
            'redirect_page',
            CASE
                WHEN settings ? 'redirect_page'
                     AND jsonb_typeof(settings->'redirect_page') <> 'object'
                    THEN settings->'redirect_page'
                WHEN COALESCE(default_settings, '{}'::jsonb) ? 'redirect_page'
                    THEN default_settings->'redirect_page'
                ELSE '"/"'::jsonb
            END,
            'admin_activation_required',
            CASE
                WHEN settings ? 'admin_activation_required'
                     AND jsonb_typeof(settings->'admin_activation_required') <> 'object'
                    THEN settings->'admin_activation_required'
                WHEN COALESCE(global_settings, '{}'::jsonb) ? 'admin_activation_required'
                    THEN global_settings->'admin_activation_required'
                ELSE '0'::jsonb
            END
        ),
    default_settings = '{}'::jsonb,
    global_settings = '{}'::jsonb
WHERE system_name = 'UserAccount';
