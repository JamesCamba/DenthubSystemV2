-- Database Backups Table (PostgreSQL)
-- Stores backup metadata and SQL content for admin export/import

CREATE TABLE IF NOT EXISTS database_backups (
    backup_id SERIAL PRIMARY KEY,
    backup_name VARCHAR(255) NOT NULL,
    backup_size INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    content TEXT
);
