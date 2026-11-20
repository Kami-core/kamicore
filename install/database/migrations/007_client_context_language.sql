CREATE TABLE IF NOT EXISTS client_contexts (
    context_id text NOT NULL,
    domain_id integer NOT NULL,
    lang_code text NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT client_contexts_pkey PRIMARY KEY (context_id, domain_id),
    CONSTRAINT client_contexts_domain_id_fkey
        FOREIGN KEY (domain_id) REFERENCES domains(domain_id) ON DELETE CASCADE,
    CONSTRAINT client_contexts_lang_code_fkey
        FOREIGN KEY (lang_code) REFERENCES languages(lang_code) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS client_contexts_domain_id_idx
    ON client_contexts (domain_id);
