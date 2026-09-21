INSERT INTO field_types (uuid, system_name, type_settings, parent_id)
SELECT
    'fa0d4922-b327-46fe-bb2b-f923911e01b1',
    'lang_code',
    '{"input":{"template":"text_input"},"output":{"template":"plain_text"},"storage":{"normalizer":"string"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["string"]},"compound_component":false}'::jsonb,
    type_id
FROM field_types
WHERE system_name='string'
ON CONFLICT (system_name) DO UPDATE
SET type_settings=EXCLUDED.type_settings,
    parent_id=EXCLUDED.parent_id;

WITH language_type AS (
    SELECT uuid
    FROM field_types
    WHERE system_name='lang_code'
), translations_data(lang_code, translated_data) AS (
    VALUES
        (
            'en',
            '{"title":"Language code","description":"System language code from the available languages."}'::jsonb
        ),
        (
            'uk',
            '{"title":"Код мови","description":"Системний код мови з переліку доступних мов."}'::jsonb
        )
)
INSERT INTO translations (entity_uuid, lang_code, translated_data)
SELECT language_type.uuid, translations_data.lang_code, translations_data.translated_data
FROM language_type
CROSS JOIN translations_data
JOIN languages ON languages.lang_code=translations_data.lang_code
ON CONFLICT (entity_uuid, lang_code) DO UPDATE
SET translated_data=EXCLUDED.translated_data,
    updated_at=now();
