DO $$
BEGIN
    IF to_regclass('public.field_variants') IS NOT NULL THEN
        DELETE FROM translations
        WHERE entity_uuid IN (
            SELECT variant_uuid
            FROM field_variants
        );
    END IF;
END
$$;

ALTER TABLE fields
DROP COLUMN IF EXISTS variant_id;

DROP TABLE IF EXISTS field_variants;
