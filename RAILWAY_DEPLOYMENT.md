# Railway.com Deployment Guide for Denthub System

## Overview
This guide explains how to deploy the Denthub Dental Clinic system to Railway.com with PostgreSQL (Neon) database.

## Prerequisites
- GitHub repository with your code
- Neon.com PostgreSQL database
- Railway.com account

## Step 1: Prepare Your Code

### Files Created for Railway:
- ✅ `Procfile` - Defines web process
- ✅ `nixpacks.toml` - Specifies PHP extensions needed
- ✅ `railway.json` - Railway configuration
- ✅ `server.php` - PHP built-in server entry point
- ✅ Updated `composer.json` - PHP extension requirements
- ✅ Updated `includes/config.php` - Environment variable support

## Step 2: Push to GitHub

Make sure all files are committed and pushed:
```bash
git add .
git commit -m "Add Railway deployment configuration"
git push origin main
```

## Step 3: Deploy on Railway

### 3.1 Create New Project
1. Go to [Railway.com](https://railway.app)
2. Click "New Project"
3. Select "Deploy from GitHub repo"
4. Choose your repository

### 3.2 Configure Environment Variables

In Railway dashboard, go to your service → Variables tab and add:

**Required Environment Variables:**
```
DATABASE_URL=postgresql://neondb_owner:npg_1MeBTYFx9XPN@ep-young-dawn-a1pvepi1-pooler.ap-southeast-1.aws.neon.tech/denthub_clinic?sslmode=require&channel_binding=require
```

**Optional (if you want to override):**
```
DB_HOST=ep-young-dawn-a1pvepi1-pooler.ap-southeast-1.aws.neon.tech
DB_USER=neondb_owner
DB_PASS=npg_1MeBTYFx9XPN
DB_NAME=denthub_clinic
DB_PORT=5432
DB_SSLMODE=require
APP_URL=https://your-app-name.up.railway.app
```

### 3.3 Configure Build Settings

Railway will automatically detect:
- **Build Command:** `composer install --no-dev --optimize-autoloader`
- **Start Command:** `php -S 0.0.0.0:$PORT -t .`

If not detected automatically, set them manually in Railway dashboard.

## Step 4: Install PHP Extensions

The `nixpacks.toml` file specifies required PHP extensions:
- `pdo` - PDO database abstraction
- `pdo_pgsql` - PostgreSQL driver (THIS FIXES THE "could not find driver" ERROR)
- `mbstring` - String functions
- `openssl` - SSL support
- `curl` - HTTP requests
- `zip` - Archive support
- `xml` - XML parsing
- `gd` - Image processing
- `intl` - Internationalization

Railway will automatically install these via Nixpacks.

## Step 5: Deploy

1. Railway will automatically start building when you connect the repo
2. Watch the build logs for any errors
3. Once deployed, Railway will provide a public URL (e.g., `https://your-app.up.railway.app`)

## Step 6: Verify Deployment

### Check Logs
1. Go to Railway dashboard → Your service → Deployments
2. Click on the latest deployment
3. Check logs for:
   - ✅ "Build successful"
   - ✅ "Starting server on port $PORT"
   - ✅ No "could not find driver" errors

### Test the Application
1. Visit your Railway URL
2. Test login functionality
3. Test database connection (should work now!)

## Troubleshooting

### Error: "could not find driver"
**Solution:** This means PDO PostgreSQL extension is missing. The `nixpacks.toml` file should fix this. Make sure:
- ✅ `nixpacks.toml` is in your repository root
- ✅ Railway is using Nixpacks builder (check build settings)
- ✅ Build logs show PHP extensions being installed

### Error: "Database connection error"
**Solution:** Check:
- ✅ `DATABASE_URL` environment variable is set correctly
- ✅ Connection string format is correct
- ✅ SSL mode is set to `require`
- ✅ Database is accessible from Railway (Neon allows external connections)

### Error: "Port already in use"
**Solution:** Railway automatically sets `$PORT` environment variable. Make sure your start command uses `$PORT`:
```bash
php -S 0.0.0.0:$PORT -t .
```

### Build Fails
**Solution:**
- Check build logs for specific errors
- Ensure `composer.json` is valid
- Verify all required files are in repository
- Check that PHP version is compatible (8.0+)

## Environment Variables Reference

| Variable | Required | Description | Example |
|----------|----------|-------------|---------|
| `DATABASE_URL` | Yes | Full PostgreSQL connection string | `postgresql://user:pass@host:port/db?sslmode=require` |
| `DB_HOST` | No | Database host (if not using DATABASE_URL) | `ep-young-dawn-...aws.neon.tech` |
| `DB_USER` | No | Database user | `neondb_owner` |
| `DB_PASS` | No | Database password | `npg_1MeBTYFx9XPN` |
| `DB_NAME` | No | Database name | `denthub_clinic` |
| `DB_PORT` | No | Database port | `5432` |
| `DB_SSLMODE` | No | SSL mode | `require` |
| `APP_URL` | No | Application URL (auto-detected from Railway) | `https://app.up.railway.app` |
| `PORT` | Auto | Server port (set by Railway) | `3000` |

## File Structure for Railway

```
DenthubSystem/
├── Procfile                 # Web process definition
├── nixpacks.toml            # PHP extensions configuration
├── railway.json             # Railway build/deploy config
├── server.php               # PHP built-in server entry
├── composer.json            # PHP dependencies + extensions
├── .htaccess                # Apache config (for Heroku buildpack)
├── index.php                # Main entry point
├── includes/
│   ├── config.php           # Updated for env vars
│   ├── database.php         # PostgreSQL PDO connection
│   └── ...
└── ...
```

## Alternative: Using Heroku Buildpack

If Nixpacks doesn't work, you can use Heroku PHP buildpack:

1. In Railway, set buildpack:
   ```
   heroku/buildpacks:heroku/php
   ```

2. Update `Procfile`:
   ```
   web: vendor/bin/heroku-php-apache2
   ```

3. Create `composer.json` with extensions (already done)

## Monitoring

### Check Application Health
- Visit your Railway URL
- Check Railway logs for errors
- Monitor database connections in Neon dashboard

### View Logs
```bash
# In Railway dashboard
Service → Deployments → Latest → View Logs
```

## Updating Your Application

1. Make changes locally
2. Commit and push to GitHub:
   ```bash
   git add .
   git commit -m "Your changes"
   git push origin main
   ```
3. Railway will automatically redeploy

## Security Notes

- ✅ Never commit `.env` files with secrets
- ✅ Use Railway environment variables for sensitive data
- ✅ SSL is required for database connection (Neon)
- ✅ Application runs over HTTPS (Railway provides SSL)

## Support

If you encounter issues:
1. Check Railway build logs
2. Check Railway runtime logs
3. Verify environment variables are set
4. Verify database is accessible
5. Check Neon database logs

---

**Deployment Date:** 2026-01-22
**Platform:** Railway.com
**Database:** Neon PostgreSQL
**Status:** ✅ Ready for Deployment
