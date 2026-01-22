# Quick Railway Setup Guide

## 🚀 Quick Steps to Deploy

### 1. Push to GitHub
```bash
git add .
git commit -m "Add Railway deployment files"
git push origin main
```

### 2. Deploy on Railway
1. Go to [railway.app](https://railway.app)
2. Click **"New Project"** → **"Deploy from GitHub repo"**
3. Select your repository

### 3. Set Environment Variable
In Railway dashboard → Your Service → Variables:

**Add this variable:**
```
DATABASE_URL=postgresql://neondb_owner:npg_1MeBTYFx9XPN@ep-young-dawn-a1pvepi1-pooler.ap-southeast-1.aws.neon.tech/denthub_clinic?sslmode=require&channel_binding=require
```

### 4. Wait for Deployment
- Railway will automatically build and deploy
- Watch the logs for "Build successful"
- Get your public URL (e.g., `https://your-app.up.railway.app`)

### 5. Test
Visit your Railway URL and test login!

---

## ✅ Files Created

- `Procfile` - Web server command
- `nixpacks.toml` - PHP extensions (fixes "could not find driver" error)
- `railway.json` - Railway configuration
- `server.php` - PHP server entry point
- Updated `composer.json` - PHP extension requirements
- Updated `includes/config.php` - Environment variable support

## 🔧 Fixes "could not find driver" Error

The `nixpacks.toml` file installs the required PHP PostgreSQL extension:
- `php82Extensions.pdo_pgsql` ← This fixes the error!

## 📝 Notes

- Railway automatically sets `$PORT` environment variable
- Database connection uses environment variables
- SSL is required for Neon database
- All PHP extensions are automatically installed

---

**That's it!** Your app should be live on Railway. 🎉
