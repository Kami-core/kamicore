CREATE TABLE seo_pages (
    page_id integer PRIMARY KEY REFERENCES pages(page_id) ON DELETE CASCADE,
    metadata jsonb NOT NULL DEFAULT '{}',
    options jsonb NOT NULL DEFAULT '{}'
);
CREATE TABLE seo_types (
    domain_id integer NOT NULL REFERENCES domains(domain_id) ON DELETE CASCADE,
    ct_id integer NOT NULL REFERENCES content_types(ct_id) ON DELETE CASCADE,
    metadata jsonb NOT NULL DEFAULT '{}',
    options jsonb NOT NULL DEFAULT '{}',
    PRIMARY KEY (domain_id, ct_id)
);
CREATE TABLE seo_domains (
    domain_id integer PRIMARY KEY REFERENCES domains(domain_id) ON DELETE CASCADE,
    settings jsonb NOT NULL DEFAULT '{}'
);
CREATE TABLE seo_schemas (
    domain_id integer NOT NULL REFERENCES domains(domain_id) ON DELETE CASCADE,
    schema_key text NOT NULL,
    title text NOT NULL,
    template text NOT NULL,
    enabled boolean NOT NULL DEFAULT true,
    PRIMARY KEY (domain_id, schema_key)
);
