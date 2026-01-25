# Maileroo Email Delivery Troubleshooting Guide

## Issue: Emails Not Being Received by Some Gmail Addresses

### Current Status
- ✅ Maileroo API is accepting requests (HTTP 200)
- ✅ API returns success: `{"success":true, "message":"The email has been queued for delivery"}`
- ✅ One Gmail address (`47hanah19@gmail.com`) receives emails successfully
- ❌ Other Gmail addresses are not receiving emails

### Possible Causes

#### 1. Gmail Spam Filtering
Gmail may be filtering emails from Maileroo's domain (`93832b22d815d4ec.maileroo.org`) as spam for some addresses but not others. This is common with:
- New email service accounts
- Shared domain email services (like Maileroo's free tier)
- Accounts with low domain reputation

**Solutions:**
- Check spam/junk folders for all recipient addresses
- Ask recipients to mark emails as "Not Spam" if found in spam
- Add sender to contacts/whitelist
- Wait 24-48 hours for Gmail to adjust filtering

#### 2. Maileroo Free Tier Limitations
Maileroo free tier has:
- **3,000 emails per month** limit
- **Hourly sending limits** that grow over time
- Emails beyond limits are **dropped without notification**

**Check:**
1. Log into Maileroo dashboard: https://app.maileroo.com
2. Go to "Email API" → "Domains" → Select your domain
3. Check "Activity" or "Logs" section for delivery status
4. Look for any error messages or delivery failures
5. Check if you've exceeded monthly/hourly limits

#### 3. Domain Reputation
New Maileroo accounts may have lower domain reputation, causing Gmail to filter emails.

**Solutions:**
- Use Maileroo for a few days to build reputation
- Send to verified/test addresses first
- Consider upgrading to paid plan for better deliverability

#### 4. Rate Limiting
If sending multiple emails quickly, you might hit hourly limits.

**Check:**
- Maileroo dashboard → Activity/Logs
- Look for rate limit errors
- Space out email sends (wait 1-2 minutes between sends)

### How to Check Delivery Status

1. **Maileroo Dashboard:**
   - Login: https://app.maileroo.com
   - Navigate to: Email API → Domains → Your Domain
   - Check "Activity" or "Email Logs" section
   - Look for delivery status (sent, delivered, bounced, failed)

2. **Reference ID Tracking:**
   - Each email gets a `reference_id` from Maileroo
   - Logs show: `Reference ID: 3cc449d02421acdfa9c85ccf`
   - Use this ID in Maileroo dashboard to track specific emails

3. **Gmail Settings:**
   - Check spam folder
   - Check "All Mail" folder
   - Check Gmail filters (Settings → Filters and Blocked Addresses)
   - Check if sender is blocked

### Immediate Actions

1. **Check Maileroo Dashboard:**
   ```
   - Login to https://app.maileroo.com
   - Go to Email API → Domains
   - Select domain: 93832b22d815d4ec.maileroo.org
   - Check Activity/Logs for delivery status
   ```

2. **Test with Different Email Providers:**
   - Try sending to Yahoo, Outlook, or other providers
   - If they work, issue is Gmail-specific

3. **Check Spam Folders:**
   - Ask recipients to check spam/junk folders
   - Check "All Mail" in Gmail

4. **Verify Account Limits:**
   - Check if monthly limit (3,000 emails) is reached
   - Check hourly sending limits

### Long-term Solutions

1. **Upgrade Maileroo Plan:**
   - Paid plans have better deliverability
   - Higher sending limits
   - Better domain reputation

2. **Use Custom Domain:**
   - Verify your own domain in Maileroo
   - Better deliverability than shared domains
   - Professional appearance

3. **Implement SPF/DKIM:**
   - Maileroo should handle this automatically
   - Verify in Maileroo dashboard → DNS Records

4. **Warm Up the Account:**
   - Send small batches initially
   - Gradually increase volume
   - Build domain reputation over time

### Alternative Solutions

If Maileroo continues to have deliverability issues:

1. **Try Different Email Service:**
   - SendGrid (100 emails/day free)
   - Brevo (300 emails/day free)
   - Resend (requires domain verification)

2. **Use SMTP Instead of API:**
   - Maileroo provides SMTP credentials
   - May have different deliverability
   - Use PHPMailer with SMTP

### Monitoring

The system now logs:
- Reference ID for each email
- Full API response
- Delivery status messages

Check Render logs for:
```
Maileroo Success: Email queued for delivery to {email} - Reference ID: {id}
```

Use the Reference ID to track emails in Maileroo dashboard.

### Contact Maileroo Support

If issues persist:
- Email: support@maileroo.com
- Provide: Reference IDs, recipient addresses, timestamps
- Ask about: Delivery status, account limits, domain reputation

---

**Last Updated:** January 25, 2026
