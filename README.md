# Denthub Dental Clinic - Online Appointment System

A comprehensive web-based appointment scheduling and patient management system for Denthub Dental Clinic.

## Features

### Patient Features
- **User Registration & Login** - Secure patient account creation and authentication
- **Service Selection** - Browse available dental services
- **Online Appointment Booking** - Book appointments with date/time selection based on availability
- **Appointment Management** - View upcoming appointments and history
- **Patient Dashboard** - Manage appointments and profile

### Staff/Admin Features
- **Staff Login** - Role-based access (Admin, Dentist, Staff)
- **Dashboard** - Overview of today's appointments, statistics
- **Appointment Management** - View, filter, update appointment status
- **Patient Records** - Search and manage patient information
- **Laboratory Cases** - Track dentures, prosthetics, and other lab cases
- **Reports** - Generate appointment and service statistics (Admin only)
- **User Management** - Manage staff accounts (Admin only)

## Technology Stack

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5.3
- **Database**: MySQL 5.7+
- **Icons**: Bootstrap Icons

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (optional, for future dependencies)

### Setup Steps

1. **Clone or Download the Project**
   ```bash
   cd DenthubSystem
   ```

2. **Create Database**
   - Open phpMyAdmin or MySQL command line
   - Copy the contents of `database/schema.txt`
   - Execute the SQL commands to create the database and tables

3. **Configure Database Connection**
   - Edit `includes/config.php`
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'denthub_clinic');
     ```

4. **Set Up Web Server**
   - For Apache: Place project in `htdocs` or `www` directory
   - For Nginx: Configure virtual host pointing to project directory
   - Ensure mod_rewrite is enabled (if using .htaccess)

5. **Set Permissions** (Linux/Mac)
   ```bash
   chmod -R 755 .
   chmod -R 777 uploads/  # If using file uploads
   ```

6. **Access the Application**
   - Patient Interface: `http://localhost/denthub/`
   - Admin Interface: `http://localhost/denthub/admin/login.php`

### Default Login Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123` (⚠️ **CHANGE THIS IMMEDIATELY IN PRODUCTION!**)

The default admin password hash in the database is for `admin123`. You should change this after first login.

## Project Structure

```
DenthubSystem/
├── admin/                 # Admin/Staff interface
│   ├── login.php
│   ├── dashboard.php
│   ├── appointments.php
│   ├── patients.php
│   ├── lab-cases.php
│   ├── reports.php
│   └── users.php
├── api/                   # API endpoints
│   └── get-available-slots.php
├── assets/                # Static assets
│   └── css/
│       └── style.css
├── database/              # Database schema
│   └── schema.txt
├── includes/              # Core PHP files
│   ├── config.php
│   ├── database.php
│   ├── auth.php
│   └── functions.php
├── patient/               # Patient interface
│   └── dashboard.php
├── index.php              # Landing page
├── register.php           # Patient registration
├── login.php              # Patient login
├── services.php           # Services listing
├── book-appointment.php   # Appointment booking
├── appointment-confirmation.php
├── contact.php
└── logout.php
```

## Database Schema

The system includes the following main tables:
- `users` - Staff/dentist accounts
- `patients` - Patient information
- `patient_accounts` - Patient login credentials
- `appointments` - Appointment records
- `services` - Dental services
- `dentists` - Dentist information
- `lab_cases` - Laboratory cases (dentures, prosthetics)
- `branches` - Clinic branches
- `time_slots` - Available appointment time slots
- `blocked_dates` - Holidays/clinic closures

## Key Features Implementation

### Appointment Booking Flow
1. Patient registers/logs in
2. Selects a service
3. Chooses preferred date
4. System shows available time slots
5. Patient selects time and submits
6. Appointment is created with "pending" status
7. Staff confirms the appointment

### Availability Checking
- System checks for existing appointments
- Blocks unavailable time slots
- Respects blocked dates (holidays)
- Can filter by specific dentist

### Role-Based Access
- **Admin**: Full access to all features
- **Dentist**: Can manage appointments and patients
- **Staff**: Can view and update appointments

## Security Notes

⚠️ **Important Security Considerations:**

1. **Change Default Password**: The default admin password is `admin123`. Change it immediately!
2. **Database Security**: Use strong database passwords
3. **HTTPS**: Use HTTPS in production
4. **Input Validation**: All user inputs are sanitized
5. **SQL Injection**: Uses prepared statements
6. **Session Security**: Sessions are properly managed
7. **Password Hashing**: Uses PHP `password_hash()` function

## Future Enhancements

- Email/SMS notifications
- Online payment integration
- Mobile app development
- Advanced reporting with charts
- Calendar view for appointments
- Patient self-service portal enhancements

## Support

For issues or questions, contact:
- Email: denthubcenter.sdc1@gmail.com
- Phone: 0916 607 0999

## License

This project is developed for Denthub Dental Clinic as a thesis project.

## Credits

Developed by:
- Aguilar, Jordan B.
- Araya, Reality M.
- Boncales, Jeson L.
- Camba, James Ronald L.
- Dela Cruz, Donnalyn F.
- Dolores, Khing Edsan M.
- Galvez, Sean Andrew D.
- Guillermo, Novie B.
- Remoto, Rachel
- Solidum, Jasmin E.
- Tabor, Aldrine G.

---

**Note**: This is a development version. Ensure proper testing before deploying to production.

