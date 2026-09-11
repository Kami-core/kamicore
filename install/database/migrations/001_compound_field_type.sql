UPDATE field_types
SET type_settings = jsonb_set(
    COALESCE(type_settings, '{}'::jsonb),
    '{compound_component}',
    'true'::jsonb,
    true
)
WHERE system_name = ANY(ARRAY['text', 'number', 'date', 'boolean', 'time']);

UPDATE field_types
SET type_settings = jsonb_set(
    COALESCE(type_settings, '{}'::jsonb),
    '{compound_component}',
    'false'::jsonb,
    true
)
WHERE system_name = ANY(ARRAY[
    'domain_id',
    'page_id',
    'content_type_id',
    'field_type_id',
    'field_id',
    'item_id',
    'user_id',
    'usergroup_id'
]);

INSERT INTO field_types (
    uuid,
    system_name,
    type_settings,
    parent_id
)
VALUES (
    '9ba25fe2-b9b9-4060-a200-fc286302c949',
    'compound',
    '{"input":{"template":"compound"},"storage":{"normalizer":"compound"},"parameters":{"components":{"type":"compound_components","title":"field_parameter_components","description":"field_parameter_components_help","default":[],"required":true}},"validation":{"rules":["compound"]}}'::jsonb,
    0
)
ON CONFLICT (system_name) DO UPDATE
SET type_settings = EXCLUDED.type_settings,
    parent_id = EXCLUDED.parent_id;

WITH compound AS (
    SELECT uuid
    FROM field_types
    WHERE system_name = 'compound'
), translations_data(lang_code, translated_data) AS (
    VALUES
        (
            'en',
            '{"title":"Compound","description":"Embedded structured value owned by its parent content item."}'::jsonb
        ),
        (
            'uk',
            '{"title":"Складене поле","description":"Вбудоване структуроване значення, яке повністю належить батьківському елементу контенту."}'::jsonb
        )
)
INSERT INTO translations (entity_uuid, lang_code, translated_data)
SELECT compound.uuid, translations_data.lang_code, translations_data.translated_data
FROM compound
CROSS JOIN translations_data
JOIN languages ON languages.lang_code = translations_data.lang_code
ON CONFLICT (entity_uuid, lang_code) DO UPDATE
SET translated_data = EXCLUDED.translated_data;
