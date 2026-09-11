TRUNCATE notification_messages;

DROP INDEX IF EXISTS notification_messages_recipient_idx;

ALTER TABLE notification_messages
    RENAME COLUMN session_id TO context_id;

CREATE INDEX notification_messages_recipient_idx
    ON notification_messages (context_id, user_id, created_at, notification_id);
