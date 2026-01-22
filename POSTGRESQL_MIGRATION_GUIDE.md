# PostgreSQL Migration Guide for Denthub System

## Overview
This guide explains how the system has been migrated from MySQL to PostgreSQL (Neon.com).

## Changes Made

### 1. Database Configuration (`includes/config.php`)
- Updated to parse Neon PostgreSQL connection string
- Extracts host, user, password, database, and port from connection string
- Sets SSL mode to `require` for secure connection

### 2. Database Connection (`includes/database.php`)
- **Complete rewrite** to use PDO with PostgreSQL
- **MySQLi Compatibility Layer** created to minimize code changes
- Provides MySQLi-like interface (`prepare()`, `bind_param()`, `execute()`, `fetch_assoc()`, etc.)
- Automatically converts MySQL-specific SQL to PostgreSQL syntax

### 3. SQL Query Updates (`includes/functions.php`)
- Updated number generation functions to use PostgreSQL syntax:
  - `SUBSTRING(string, start)` → `SUBSTRING(string FROM start)`
  - `UNSIGNED` → `INTEGER`

## Connection String Format

The system uses this Neon connection string:
```
postgresql://neondb_owner:npg_1MeBTYFx9XPN@ep-young-dawn-a1pvepi1-pooler.ap-southeast-1.aws.neon.tech/denthub_clinic?sslmode=require&channel_binding=require
```

## Database Setup

### Step 1: Run PostgreSQL Migration Script
1. Go to your Neon dashboard
2. Open the SQL Editor
3. Copy and paste the entire PostgreSQL migration script you provided
4. Execute it to create all tables and insert sample data

### Step 2: Verify Connection
The system will automatically connect when you access any page. Check for:
- No database connection errors
- Pages load correctly
- Login works

### Step 3: Test Key Functions
1. **Login** - Test admin, dentist, and patient login
2. **Patient Registration** - Register a new patient
3. **Appointment Booking** - Book an appointment
4. **Number Generation** - Verify patient/appointment numbers generate correctly

## Compatibility Layer Features

The MySQLi compatibility layer handles:

### Statement Methods
- `prepare($sql)` - Prepares SQL statement
- `bind_param($types, ...$params)` - Binds parameters (i, s, d, b)
- `execute()` - Executes the statement
- `get_result()` - Returns result object

### Result Methods
- `fetch_assoc()` - Fetches associative array
- `num_rows` - Returns number of rows
- `fetch_all()` - Returns all rows

### Connection Methods
- `query($sql)` - Executes direct query
- `prepare($sql)` - Prepares statement
- `escape($string)` - Escapes string
- `getLastInsertId()` - Gets last insert ID

## Automatic SQL Conversion

The system automatically converts:
- `UNSIGNED` → removed (PostgreSQL doesn't have UNSIGNED)
- `SUBSTRING(string, start)` → `SUBSTRING(string FROM start)`
- `CAST(...AS UNSIGNED)` → `CAST(...AS INTEGER)`

## Troubleshooting

### Connection Errors
If you see "Database connection error":
1. Verify the connection string in `includes/config.php`
2. Check that Neon database is accessible
3. Ensure SSL is enabled (required by Neon)

### SQL Errors
If you see SQL syntax errors:
1. Check the error message - it will show the actual SQL being executed
2. The compatibility layer handles most conversions automatically
3. If you see PostgreSQL-specific errors, check the query syntax

### Number Generation Issues
If patient/appointment numbers aren't generating:
1. Verify the `SUBSTRING` syntax in `includes/functions.php`
2. Check that tables have data (at least one row to calculate MAX)

## Testing Checklist

- [ ] Database connection successful
- [ ] Admin login works
- [ ] Dentist login works
- [ ] Patient login works
- [ ] Patient registration works
- [ ] Email verification works
- [ ] Appointment booking works
- [ ] Number generation works (PAT, APT, LAB)
- [ ] Appointment status updates work
- [ ] Lab case management works
- [ ] Search functionality works

## Notes

- The system maintains backward compatibility with existing code
- No changes needed to most PHP files (they still use MySQLi-like syntax)
- Only database connection layer changed
- All existing features should work as before

## Support

If you encounter issues:
1. Check PHP error logs
2. Enable error display in `includes/config.php` (already enabled)
3. Check Neon database logs
4. Verify all tables exist and have correct structure

---

**Migration Date:** 2026-01-22
**Database:** PostgreSQL (Neon.com)
**Status:** ✅ Complete
