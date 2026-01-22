# Quick Setup Guide - Denthub Dental Clinic System

## Step 1: Database Setup

1. Open your MySQL client (phpMyAdmin, MySQL Workbench, or command line)
2. Open the file `database/schema.txt`
3. Copy ALL the SQL commands from the file
4. Execute them in your MySQL client
5. This will create:
   - Database: `denthub_clinic`
   - All necessary tables
   - Default admin user (username: `admin`, password: `admin123`)
   - Default services
   - Default time slots

## Step 2: Configure Database Connection

1. Open `includes/config.php`
2. Update these lines with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'denthub_clinic');
   ```

## Step 3: Web Server Setup

### Option A: Using XAMPP/WAMP/MAMP (Windows/Mac)

1. Copy the entire `DenthubSystem` folder to:
   - XAMPP: `C:\xampp\htdocs\DenthubSystem`
   - WAMP: `C:\wamp\www\DenthubSystem`
   - MAMP: `/Applications/MAMP/htdocs/DenthubSystem`

2. Start Apache and MySQL services

3. Access the system:
   - Patient Interface: `http://localhost/DenthubSystem/`
   - Admin Interface: `http://localhost/DenthubSystem/admin/login.php`

### Option B: Using PHP Built-in Server (Development Only)

1. Open terminal/command prompt
2. Navigate to project directory:
   ```bash
   cd DenthubSystem
   ```
3. Start PHP server:
   ```bash
   php -S localhost:8000
   ```
4. Access the system:
   - Patient Interface: `http://localhost:8000/`
   - Admin Interface: `http://localhost:8000/admin/login.php`

## Step 4: First Login

1. Go to `http://localhost/DenthubSystem/admin/login.php`
2. Login with:
   - Username: `admin`
   - Password: `admin123`
3. **IMPORTANT**: Change the admin password immediately after first login!

## Step 5: Test Patient Registration

1. Go to the main page: `http://localhost/DenthubSystem/`
2. Click "Register"
3. Fill in patient information
4. Register and login
5. Try booking an appointment

## Troubleshooting

### Database Connection Error
- Check if MySQL is running
- Verify database credentials in `includes/config.php`
- Make sure database `denthub_clinic` exists

### Page Not Found / 404 Error
- Check if mod_rewrite is enabled (for Apache)
- Verify file paths are correct
- Check web server error logs

### Permission Denied
- On Linux/Mac, set proper permissions:
  ```bash
  chmod -R 755 .
  ```

### Time Slots Not Loading
- Check if `time_slots` table has data
- Verify API endpoint: `api/get-available-slots.php` is accessible
- Check browser console for JavaScript errors

## Next Steps

1. **Add Dentists**: Create user accounts for dentists in the admin panel
2. **Configure Services**: Edit services in the database or add via admin panel
3. **Set Blocked Dates**: Add holidays/clinic closures in `blocked_dates` table
4. **Customize**: Update clinic information in `branches` table

## Security Checklist

- [ ] Change default admin password
- [ ] Use strong database passwords
- [ ] Enable HTTPS in production
- [ ] Set proper file permissions
- [ ] Disable error display in production (set `display_errors` to 0 in `config.php`)
- [ ] Regular database backups

## Support

For issues or questions:
- Email: denthubcenter.sdc1@gmail.com
- Phone: 0916 607 0999

