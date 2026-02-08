# Render.com Deployment Guide for DenthubSystem

## Quick Setup Steps

### 1. Create Web Service on Render

Fill out the form:
- **Name**: `DenthubSystem` ✅
- **Project**: Leave empty (optional)
- **Environment**: Leave default
- **Language**: `Docker` ✅
- **Branch**: `main` ✅
- **Region**: `Oregon (US West)` ✅
- **Root Directory**: Leave empty ✅
- **Instance Type**: Select **Free** (for Maileroo)

Click **"Create Web Service"**

### 2. Set Environment Variables

After creating the service, go to **Environment** tab and add:

#### Database (Required)
```
DATABASE_URL=postgresql://neondb_owner:npg_1MeBTYFx9XPN@ep-young-dawn-a1pvepi1-pooler.ap-southeast-1.aws.neon.tech/denthub_clinic?sslmode=require&channel_binding=require
```

#### Email Configuration (Maileroo - Free)
```
MAIL_DRIVER=maileroo
MAILEROO_API_KEY=1b0fdf022479e8cd55c992f5dd3efbf41593571fb782ddecd2dbd54cf235c449
MAILEROO_FROM_EMAIL=denthub@93832b22d815d4ec.maileroo.org
MAILEROO_FROM_NAME=Denthub Dental Clinic
```

**Important:** 
- `MAILEROO_FROM_EMAIL` must use your Maileroo domain (`denthub@93832b22d815d4ec.maileroo.org`)
- You CANNOT use Gmail addresses - Maileroo requires their domain
- The email will appear as "Denthub Dental Clinic <denthub@93832b22d815d4ec.maileroo.org>"

### 3. Deploy

1. Click **"Save Changes"** in Environment Variables
2. Render will automatically start building and deploying
3. Wait 2-3 minutes for deployment to complete
4. Check **Logs** tab for any errors

### 4. Test Email Verification

1. Once deployed, visit your Render URL
2. Try registering a new account
3. You should receive a verification email from `denthub@93832b22d815d4ec.maileroo.org`
4. Enter the code to complete registration

## Troubleshooting

### Email Not Sending?

1. **Check Render Logs**:
   - Go to Render dashboard → Your service → **Logs**
   - Look for `Maileroo Debug:` or `Maileroo Error:` lines
   - Share any errors you see

2. **Verify Environment Variables**:
   - Make sure `MAIL_DRIVER=maileroo` (not `smtp` or `sendgrid`)
   - Make sure `MAILEROO_API_KEY` is set correctly
   - Make sure `MAILEROO_FROM_EMAIL` uses your Maileroo domain (not Gmail)

3. **Check Maileroo Dashboard**:
   - Verify your SMTP account is active
   - Check if there are any sending limits or restrictions

### Database Connection Issues?

- Verify `DATABASE_URL` is set correctly
- Check Neon dashboard to ensure database is active
- Check Render logs for database connection errors

## Summary

**Environment Variables Needed:**
- `DATABASE_URL` (from Neon)
- `MAIL_DRIVER=maileroo`
- `MAILEROO_API_KEY` (your sending key)
- `MAILEROO_FROM_EMAIL=denthub@93832b22d815d4ec.maileroo.org` (your Maileroo email)
- `MAILEROO_FROM_NAME=Denthub Dental Clinic`
- `BACKUP_CRON_KEY` (optional, for auto backup - set a secret string to enable)

**Note:** `RENDER_EXTERNAL_URL` is set automatically by Render (e.g. `https://denthubsystemv2-5.onrender.com`). If the backup cron URL shows localhost, ensure your service is deployed; you can also set `APP_URL` manually in Environment Variables.

### Auto Backup (Every 5 Hours)

1. Set `BACKUP_CRON_KEY` in Environment Variables (e.g. a random string like `mySecretBackupKey123`)
2. Add a Cron Job (e.g. at [cron-job.org](https://cron-job.org)):
   - URL: `https://your-app.onrender.com/cron/backup.php?key=YOUR_BACKUP_CRON_KEY`
   - Schedule: Every 5 hours
3. Backups are stored in the database and can be viewed/downloaded from Admin → Backups

That's it! Once deployed, your email verification should work.
