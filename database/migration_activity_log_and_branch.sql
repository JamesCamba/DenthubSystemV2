-- Activity log for admin: who did what (password/email/phone/status changes, login success/fail)
CREATE TABLE IF NOT EXISTS activity_log (
    log_id serial PRIMARY KEY,
    action_type varchar(50) NOT NULL,
    actor_type varchar(20) NOT NULL DEFAULT 'user',
    actor_id integer,
    username varchar(50),
    full_name varchar(100),
    role varchar(20),
    details text,
    ip_address varchar(45),
    user_agent text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_activity_log_action ON activity_log (action_type);
CREATE INDEX IF NOT EXISTS idx_activity_log_actor ON activity_log (actor_type, actor_id);

-- Login rate limiting: track failed attempts per device (IP + optional fingerprint)
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id serial PRIMARY KEY,
    identifier varchar(64) NOT NULL,
    attempt_type varchar(20) NOT NULL DEFAULT 'staff',
    failed_count integer DEFAULT 0,
    captcha_passed_at timestamp,
    locked_until timestamp,
    last_attempt_at timestamp DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_login_attempts_identifier_type ON login_attempts (identifier, attempt_type);
CREATE INDEX IF NOT EXISTS idx_login_attempts_locked ON login_attempts (locked_until);

-- Add second branch: Tondo, Manila (run once)
INSERT INTO branches (branch_name, address, phone, email, is_active)
SELECT 'Denthub Tondo', '1876 Velasquez Street Barangay 92 Zone 8 Tondo, Manila, Philippines, 1013', NULL, NULL, true
WHERE NOT EXISTS (SELECT 1 FROM branches WHERE address LIKE '%Velasquez%' OR branch_name = 'Denthub Tondo');
