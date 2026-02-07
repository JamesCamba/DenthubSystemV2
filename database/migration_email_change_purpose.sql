-- Allow 'email_change' purpose in email_verification_codes (for patient email change verification)
-- Run this once if patient email change verification is used.

ALTER TABLE email_verification_codes DROP CONSTRAINT IF EXISTS purpose_check;
ALTER TABLE email_verification_codes ADD CONSTRAINT purpose_check
  CHECK (purpose IN ('registration', 'password_reset', 'email_change'));
