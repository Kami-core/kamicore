CREATE TABLE IF NOT EXISTS vc_content_types (
    ct_id INTEGER PRIMARY KEY REFERENCES content_types(ct_id) ON DELETE CASCADE,
    single_template TEXT,
    list_template TEXT,
    tree_template TEXT
);
