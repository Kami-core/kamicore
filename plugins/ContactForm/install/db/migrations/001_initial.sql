CREATE TABLE IF NOT EXISTS contact_submissions (
    submission_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    domain_id BIGINT NOT NULL REFERENCES domains(domain_id) ON DELETE CASCADE,
    content_type_id BIGINT REFERENCES content_types(ct_id) ON DELETE SET NULL,
    item_id BIGINT REFERENCES content_items(item_id) ON DELETE SET NULL,
    context_id TEXT NOT NULL,
    user_id BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    ip_address INET,
    user_agent TEXT,
    referer TEXT,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    status VARCHAR(24) NOT NULL DEFAULT 'submitted'
        CHECK (status IN ('submitted', 'rate_limited')),
    delivery_status JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS contact_submissions_ip_created_idx
    ON contact_submissions (ip_address, created_at DESC)
    WHERE status = 'submitted';

CREATE INDEX IF NOT EXISTS contact_submissions_context_created_idx
    ON contact_submissions (context_id, created_at DESC)
    WHERE status = 'submitted';

CREATE INDEX IF NOT EXISTS contact_submissions_domain_created_idx
    ON contact_submissions (domain_id, created_at DESC);
