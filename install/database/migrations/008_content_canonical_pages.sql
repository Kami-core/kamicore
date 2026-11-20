CREATE TABLE IF NOT EXISTS content_canonical_pages (
    domain_id integer NOT NULL REFERENCES domains(domain_id) ON DELETE CASCADE,
    item_id bigint NOT NULL REFERENCES content_items(item_id) ON DELETE CASCADE,
    page_id integer REFERENCES pages(page_id) ON DELETE CASCADE,
    dependency_item_id bigint REFERENCES content_items(item_id) ON DELETE CASCADE,
    PRIMARY KEY (domain_id, item_id)
);

CREATE INDEX IF NOT EXISTS content_canonical_pages_item_id_idx
    ON content_canonical_pages (item_id);

CREATE INDEX IF NOT EXISTS content_canonical_pages_page_id_idx
    ON content_canonical_pages (page_id);

CREATE INDEX IF NOT EXISTS content_canonical_pages_dependency_item_id_idx
    ON content_canonical_pages (dependency_item_id);
