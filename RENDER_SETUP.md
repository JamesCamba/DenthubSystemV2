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
- **Instance Type**: Select **Free** (for Maileroo) or **Starter** ($7/month for Gmail SMTP)

Click **"Create Web Service"**

### 2. Set Environment Variables

After creating the service, go to **Environment** tab and add:

#### Database (Required)
```
DATABASE_URL=postgresql://neondb_owner:npg_1MeBTYFx9XPN@ep-young-dawn-a1pvepi1-pooler.ap-southeast-1.aws.neon.tech/denthub_clinic?sslmode=require&channel_binding=require
```

#### Email Configuration - Choose ONE Option:

**Option 1: Maileroo (Free - RECOMMENDED!)**
```
MAIL_DRIVER=maileroo
MAILEROO_API_KEY=1b0fdf022479e8cd55c992f5dd3efbf41593571fb782ddecd2dbd54cf235c449
MAILEROO_FROM_EMAIL=dentalclinicdenthub@gmail.com
MAILEROO_FROM_NAME=Denthub Dental Clinic
```

**Option 2: Gmail SMTP (Requires Starter Plan $7/month)**
```
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=dentalclinicdenthub@gmail.com
MAIL_PASSWORD=hakp xtdl gksu ooxs
MAIL_ENCRYPTION=tls
```

**Option 3: SendGrid (Free Alternative)**
```
MAIL_DRIVER=sendgrid
SENDGRID_API_KEY=your_sendgrid_api_key_here
SENDGRID_FROM_EMAIL=dentalclinicdenthub@gmail.com
SENDGRID_FROM_NAME=Denthub Dental Clinic
```

### 3. Deploy

1. Click **"Save Changes"** in Environment Variables
2. Render will automatically start building and deploying
3. Wait 2-3 minutes for deployment to complete
4. Check **Logs** tab for any errors

### 4. Test Email Verification

1. Once deployed, visit your Render URL
2. Try registering a new account
3. You should receive a verification email
4. Enter the code to complete registration

## Troubleshooting

### Email Not Sending?

1. **Check Render Logs**:
   - Go to Render dashboard → Your service → **Logs**
   - Look for `Maileroo Error:`, `SendGrid Error:`, or `Mailer Error:`
   - Share any errors you see

2. **Verify Environment Variables**:
   - Make sure `MAIL_DRIVER` matches your chosen option (`maileroo`, `smtp`, or `sendgrid`)
   - Make sure API keys are set correctly
   - Make sure `FROM_EMAIL` is set

### Database Connection Issues?

- Verify `DATABASE_URL` is set correctly
- Check Neon dashboard to ensure database is active
- Check Render logs for database connection errors

## Summary

**Recommended Setup:**
- **Instance Type**: Free
- **Email**: Maileroo (simplest free option)
- **Database**: Neon PostgreSQL (already set up)

**Environment Variables Needed:**
- `DATABASE_URL` (from Neon)
- `MAIL_DRIVER=maileroo`
- `MAILEROO_API_KEY` (your Maileroo key)
- `MAILEROO_FROM_EMAIL=dentalclinicdenthub@gmail.com`
- `MAILEROO_FROM_NAME=Denthub Dental Clinic`

That's it! Once deployed, your email verification should work.
