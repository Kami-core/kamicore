DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM fields WHERE system_name = 'article_preview'
    ) AND EXISTS (
        SELECT 1 FROM fields WHERE system_name = 'article_image'
    ) THEN
        RAISE EXCEPTION
            'Cannot rename article_preview to article_image: target field already exists';
    END IF;
END
$$;

UPDATE fields
SET system_name = 'article_image'
WHERE system_name = 'article_preview';

UPDATE content_types
SET schema = jsonb_set(
    schema,
    '{fields}',
    ((schema->'fields') - 'article_preview'::text)
        || CASE
            WHEN (schema->'fields') ? 'article_image' THEN '{}'::jsonb
            ELSE jsonb_build_object(
                'article_image',
                schema->'fields'->'article_preview'
            )
        END,
    true
)
WHERE coalesce(schema->'fields', '{}'::jsonb) ? 'article_preview';

UPDATE content_types
SET schema = jsonb_set(schema, '{title_field}', '"article_image"'::jsonb, true)
WHERE schema->>'title_field' = 'article_preview';

UPDATE content_types
SET schema = jsonb_set(schema, '{summary_field}', '"article_image"'::jsonb, true)
WHERE schema->>'summary_field' = 'article_preview';

UPDATE content_items
SET common_data = (coalesce(common_data, '{}'::jsonb) - 'article_preview'::text)
    || CASE
        WHEN coalesce(common_data, '{}'::jsonb) ? 'article_image' THEN '{}'::jsonb
        ELSE jsonb_build_object(
            'article_image',
            common_data->'article_preview'
        )
    END
WHERE coalesce(common_data, '{}'::jsonb) ? 'article_preview';

UPDATE translations
SET translated_data = (coalesce(translated_data, '{}'::jsonb) - 'article_preview'::text)
    || CASE
        WHEN coalesce(translated_data, '{}'::jsonb) ? 'article_image' THEN '{}'::jsonb
        ELSE jsonb_build_object(
            'article_image',
            translated_data->'article_preview'
        )
    END
WHERE coalesce(translated_data, '{}'::jsonb) ? 'article_preview';

UPDATE translations
SET translated_data = jsonb_set(
    translated_data,
    '{schema,fields}',
    ((translated_data#>'{schema,fields}') - 'article_preview'::text)
        || CASE
            WHEN (translated_data#>'{schema,fields}') ? 'article_image' THEN '{}'::jsonb
            ELSE jsonb_build_object(
                'article_image',
                translated_data#>'{schema,fields,article_preview}'
            )
        END,
    true
)
WHERE coalesce(translated_data#>'{schema,fields}', '{}'::jsonb) ? 'article_preview';

UPDATE translations translation
SET translated_data = jsonb_set(
    coalesce(translation.translated_data, '{}'::jsonb),
    '{title}',
    to_jsonb(
        CASE translation.lang_code
            WHEN 'uk' THEN 'Зображення статті'
            ELSE 'Article image'
        END::text
    ),
    true
)
FROM fields field
WHERE translation.entity_uuid = field.uuid
  AND field.system_name = 'article_image'
  AND (
      (translation.lang_code = 'en'
       AND translation.translated_data->>'title' = 'Article preview')
      OR
      (translation.lang_code = 'uk'
       AND translation.translated_data->>'title' = 'Обкладинка')
  );
