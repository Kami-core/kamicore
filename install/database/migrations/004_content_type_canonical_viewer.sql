ALTER TABLE content_types
ADD COLUMN IF NOT EXISTS canonical_viewer_plugin_id integer;

DO $$
BEGIN
    ALTER TABLE content_types
    ADD CONSTRAINT content_types_canonical_viewer_plugin_id_fkey
    FOREIGN KEY (canonical_viewer_plugin_id)
    REFERENCES plugins(plugin_id)
    ON DELETE SET NULL;
EXCEPTION
    WHEN duplicate_object THEN NULL;
END
$$;
