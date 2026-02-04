# Denthub Dental Clinic System - Complete Workflow Documentation

## Overview
This document explains the complete workflow for each actor (Patient, Admin, Dentist) in the Denthub Dental Clinic Management System.

---

## 1. PATIENT WORKFLOW

### 1.1 Registration Process

**Step 1: Account Registration**
- Patient visits `register.php`
- Fills out registration form with:
  - Personal information (name, email, phone, birthdate, gender, address)
  - Password (minimum 8 characters)
- Submits form

**Step 2: Email Verification**
- System creates patient record in `patients` table
- Creates unverified account in `patient_accounts` table
- Generates 6-digit verification code
- Saves code in `email_verification_codes` table (expires in 10 minutes)
- Sends verification email via PHPMailer to patient's email address
- Patient is redirected to verification page (`register.php?step=verify&email=...`)

**Step 3: Code Verification**
- Patient enters 6-digit code received via email
- System validates code:
  - Checks if code exists and matches email
  - Verifies code hasn't expired (10 minutes)
  - Ensures code hasn't been used
- If valid:
  - Marks code as used
  - Updates `patient_accounts.is_verified = 1`
  - Redirects to success page with login link
- If invalid:
  - Shows error message
  - Patient can try again or re-register

**Step 4: First Login**
- Patient visits `login.php` (or unified `login-unified.php`)
- Enters email and password
- System authenticates and creates session
- Redirects to `patient/dashboard.php`

### 1.2 Booking Appointment Process

**Step 1: Access Booking Page**
- Patient must be logged in
- Navigates to `book-appointment.php`

**Step 2: Select Service**
- Patient selects service from dropdown
- System dynamically loads dentists who have mastery in selected service
- Only dentists capable of performing that service are shown

**Step 3: Choose Dentist (Optional)**
- Patient can select specific dentist or choose "Any Available Dentist"
- If specific dentist selected, only their available slots are shown

**Step 4: Select Date and Time**
- Patient selects preferred date (must be tomorrow or later)
- System checks:
  - Date is not blocked
  - Dentist schedule (if specific dentist selected)
  - Existing appointments for that date/time
- Available time slots are dynamically loaded via AJAX

**Step 5: Submit Appointment**
- Patient fills optional "Reason for Visit"
- Submits appointment request
- System:
  - Generates unique appointment number (APT000001, etc.)
  - Creates appointment with status "pending"
  - Redirects to confirmation page

**Step 6: Appointment Confirmation**
- Patient views appointment details
- Status is "pending" until admin/dentist confirms
- Patient can view appointment in their dashboard

### 1.3 Patient Dashboard Features

**View Appointments**
- See all appointments (past and upcoming)
- Filter by status (pending, confirmed, completed, cancelled)
- View appointment details

**Profile Management**
- Update personal information
- Change password
- View appointment history

---

## 2. ADMIN WORKFLOW

### 2.1 Login Process

**Step 1: Access Admin Login**
- Admin visits `login-unified.php` or `admin/login.php`
- Enters username/email and password
- System checks `users` table:
  - Validates credentials
  - Checks if account is active
  - Verifies role (admin, staff, or dentist)

**Step 2: Role-Based Redirect**
- If role is "admin" or "staff": Redirects to `admin/dashboard.php`
- If role is "dentist": Redirects to `dentist/dashboard.php`
- Session stores: user_id, username, full_name, role, branch_id

### 2.2 Creating Dentist Accounts

**Step 1: Navigate to User Management**
- Admin goes to `admin/users.php`
- Clicks "Add New User" button

**Step 2: Fill Dentist Information**
- Select role: "Dentist"
- Enter:
  - Username (unique)
  - Email (unique)
  - Full Name
  - Phone (optional)
  - Branch
  - License Number
  - Specialization (e.g., "General Dentistry, Orthodontics")

**Step 3: Assign Service Mastery**
- Admin selects services this dentist can perform
- Only selected services will be available when patients book appointments
- This ensures patients only see dentists capable of their needed service

**Step 4: Account Creation**
- System:
  - Generates temporary password (8 characters)
  - Creates user account in `users` table
  - Creates dentist record in `dentists` table
  - Links services in `dentist_service_mastery` table
  - Creates default schedule (Monday-Friday, 9 AM - 5 PM) in `dentist_schedules`
  - Sends email to dentist with credentials

**Step 5: Email Notification**
- Dentist receives email with:
  - Username
  - Temporary password
  - Login URL
  - Instructions to change password
  - Contact information (dentalclinicdenthub@gmail.com)

### 2.3 Appointment Management

**View All Appointments**
- Admin sees all appointments in `admin/appointments.php`
- Can filter by:
  - Status
  - Date
  - Patient name or reference number

**Update Appointment Status**
- Admin can change status:
  - Pending → Confirmed
  - Confirmed → Completed
  - Any status → Cancelled
- Can assign dentist to appointment
- Can add notes

**View Appointment Details**
- See complete patient information
- View service details
- See assigned dentist (if any)
- View appointment history

### 2.4 Other Admin Functions

**Patient Management**
- View all patients
- View patient profiles
- See patient appointment history

**User Management**
- View all users (admin, staff, dentists)
- Create new users
- Edit user information
- Deactivate/activate accounts

**Reports**
- View system statistics
- Generate reports

---

## 3. DENTIST WORKFLOW

### 3.1 Initial Setup (First Time)

**Step 1: Receive Account Credentials**
- Dentist receives email from admin with:
  - Username
  - Temporary password
  - Login instructions

**Step 2: First Login**
- Dentist visits `login-unified.php`
- Enters username/email and temporary password
- System authenticates and redirects to `dentist/dashboard.php`

**Step 3: Change Password (Recommended)**
- Dentist should change temporary password immediately
- Navigate to profile settings
- Update password

### 3.2 Viewing Appointments

**Dashboard Overview**
- See today's appointments count
- View pending appointments
- See monthly completed appointments
- Quick view of today's schedule

**My Appointments Page**
- View all appointments assigned to this dentist
- Filter by:
  - Status
  - Date
- See appointment details:
  - Patient information
  - Service type
  - Date and time
  - Status

### 3.3 Managing Appointments

**Confirm Appointment**
- Dentist can change status from "pending" to "confirmed"
- System checks dentist's schedule:
  - Verifies appointment time is within dentist's available hours
  - Checks day of week matches dentist schedule
  - Ensures no double-booking

**Complete Appointment**
- After service is performed:
  - Change status to "completed"
  - Add notes about the procedure
  - System records completion date

**Cancel/Reschedule**
- Dentist can cancel appointment
- Can add notes explaining cancellation
- Status changes to "cancelled"

**View Appointment Details**
- See complete patient information
- View service details
- See reason for visit
- Add/update notes
- Update status

### 3.4 Schedule Management

**View Schedule**
- See weekly schedule
- View available time slots
- See blocked/unavailable times

**Note**: Currently, default schedule is set by admin. Future enhancement could allow dentists to manage their own schedules.

---

## 4. SYSTEM FEATURES

### 4.1 Email Verification System

**Purpose**: Prevent dummy/fake registrations

**Process**:
1. 6-digit code generated on registration
2. Code stored with expiration (10 minutes)
3. Email sent via PHPMailer using Gmail SMTP
4. Patient must verify before account is activated
5. Code can only be used once

**Email Configuration**:
- Gmail: dentalclinicdenthub@gmail.com
- App Name: Denthub2FA
- App Password: hakp xtdl gksu ooxs

### 4.2 Service Mastery System

**Purpose**: Ensure only qualified dentists are assigned to services

**How It Works**:
- Admin assigns services to dentists during account creation
- When patient selects a service, only dentists with that mastery are shown
- Prevents booking appointments with unqualified dentists
- Stored in `dentist_service_mastery` table (many-to-many relationship)

### 4.3 Schedule-Based Availability

**Purpose**: Respect dentist working hours

**How It Works**:
- Each dentist has a schedule in `dentist_schedules` table
- Defines day of week and time range (e.g., Monday, 9 AM - 5 PM)
- When checking availability:
  - System checks if appointment time is within dentist's schedule
  - Verifies day of week matches
  - Checks for existing appointments
  - Only shows available slots

### 4.4 Unified Login System

**Purpose**: Single login page for all user types

**How It Works**:
- `login-unified.php` accepts email or username
- Tries patient login first (checks `patient_accounts`)
- If fails, tries staff/dentist login (checks `users`)
- Automatically redirects based on role:
  - Patient → `patient/dashboard.php`
  - Admin/Staff → `admin/dashboard.php`
  - Dentist → `dentist/dashboard.php`

### 4.5 Appointment Status Flow

**Status Progression**:
1. **Pending**: Initial state when patient books
2. **Confirmed**: Admin or dentist confirms appointment
3. **Completed**: Service has been performed
4. **Cancelled**: Appointment cancelled by patient, admin, or dentist
5. **No Show**: Patient didn't show up

---

## 5. DATABASE STRUCTURE

### Key Tables:

**patients**: Patient personal information
**patient_accounts**: Patient login credentials and verification status
**users**: Staff/dentist/admin accounts
**dentists**: Dentist-specific information (license, specialization)
**dentist_service_mastery**: Links dentists to services they can perform
**dentist_schedules**: Dentist availability (day of week, time ranges)
**appointments**: Appointment records
**services**: Available dental services
**email_verification_codes**: Email verification codes with expiration

### Relationships:
- `dentists.user_id` → `users.user_id`
- `appointments.dentist_id` → `dentists.dentist_id`
- `appointments.patient_id` → `patients.patient_id`
- `appointments.service_id` → `services.service_id`
- `dentist_service_mastery.dentist_id` → `dentists.dentist_id`
- `dentist_service_mastery.service_id` → `services.service_id`

---

## 6. SECURITY FEATURES

1. **Password Hashing**: All passwords use PHP `password_hash()` with bcrypt
2. **Email Verification**: Prevents fake registrations
3. **Session Management**: Secure session handling
4. **SQL Injection Prevention**: All queries use prepared statements
5. **XSS Protection**: All output is sanitized with `htmlspecialchars()`
6. **Role-Based Access**: Pages check user roles before allowing access
7. **Temporary Passwords**: New accounts get temporary passwords that should be changed

---

## 7. FILE ORGANIZATION

```
DenthubSystem/
├── admin/              # Admin/Staff pages
├── dentist/            # Dentist pages
├── patient/            # Patient pages
├── api/                # API endpoints
├── includes/           # Core PHP files
│   ├── auth.php       # Authentication functions
│   ├── config.php     # Configuration
│   ├── database.php   # Database connection
│   ├── functions.php  # Helper functions
│   └── mailer.php      # Email service
├── templates/          # Email templates
├── assets/            # CSS, JS, images
└── database_migration.txt  # Database updates
```

---

## 8. DUMMY DATA

### Dentists Created:
1. **Dr. Jan Charina Almendral DMD**
   - Username: dr.almendral
   - Services: Consultation, Cleaning, Whitening, Orthodontics

2. **Dr. Alyssa Ann Bastila-on DMD**
   - Username: dr.bastilaon
   - Services: Wisdom Tooth Removal, Root Canal, Implant

3. **Dr. Abegail Iatube DMD**
   - Username: dr.iatube
   - Services: Restoration, Whitening, Gum Treatment

4. **Dr. John Patrick Cinco DMD**
   - Username: dr.cinco
   - Services: Restoration, Root Canal, Dentures

**Default Password**: `password` (should be changed on first login)

**Default Schedule**: Monday to Friday, 9:00 AM - 5:00 PM

---

## 9. TROUBLESHOOTING

### Email Not Sending
- Check Gmail app password is correct
- Verify SMTP settings in `includes/mailer.php`
- Check server can connect to smtp.gmail.com:587

### Dentist Not Showing in Booking
- Verify dentist has service mastery for selected service
- Check dentist account is active
- Ensure dentist has schedule for selected day

### Login Issues
- Verify email/username is correct
- Check account is verified (for patients)
- Ensure account is active
- Try unified login page

### Appointment Not Available
- Check if date is blocked
- Verify dentist schedule for that day
- Check for existing appointments at that time
- Ensure date is not in the past

---

## 10. FUTURE ENHANCEMENTS

1. Dentist self-service schedule management
2. SMS notifications for appointments
3. Patient appointment rescheduling
4. Online payment integration
5. Treatment history and records
6. Lab case tracking integration
7. Advanced reporting and analytics
8. Multi-branch support enhancements

---

**Last Updated**: January 2026
**System Version**: 2.0
