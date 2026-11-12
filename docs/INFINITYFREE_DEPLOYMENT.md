# InfinityFree Hosting Deployment Guide

This guide will help you deploy your MyStudy.com application to InfinityFree hosting and configure it properly.

## 🚀 Quick Setup Steps

### 1. Upload Files to InfinityFree
- Upload all files from your `c:\xampp\htdocs\mystudy.com` folder to the root directory of your InfinityFree hosting
- Make sure the folder structure is maintained:
  ```
  /public_html/
  ├── admin/
  ├── assets/
  ├── config/
  ├── controllers/
  ├── includes/
  ├── models/
  ├── views/
  ├── contact.php
  ├── index.php
  ├── login.php
  ├── maintenance.php
  └── ... (other files)
  ```

### 2. Configure Database on InfinityFree
- Log into your InfinityFree control panel
- Go to MySQL Database section
- Create a new database named: `mystudy_ng`
- Note the database credentials provided by InfinityFree

### 3. Update Database Configuration
Edit `config/database.php` with your InfinityFree database credentials:

```php
// Replace these values with your InfinityFree database info
define('DB_HOST', 'your-infinityfree-db-host');
define('DB_USER', 'your-database-username');
define('DB_PASS', 'your-database-password');
define('DB_NAME', 'mystudy_ng');
```

### 4. Update Email Configuration
Edit `config/email-config.php` with your InfinityFree email settings:

```php
// Update these values for InfinityFree hosting
define('BREVO_API_KEY', 'your-brevo-api-key');
define('BREVO_FROM_EMAIL', 'your-email@yourdomain.com');
define('BREVO_FROM_NAME', 'MyStudy');
```

### 5. Set File Permissions
- Ensure `assets/uploads/` folder is writable (755 permissions)
- Ensure `logs/` folder is writable (755 permissions)

## 🔧 Configuration Details

### Environment Detection
The application automatically detects:
- **Localhost**: `localhost` or `127.0.0.1`
- **InfinityFree**: Any domain containing `infinityfree`
- **Production**: Any other domain

### URL Generation
- **Localhost**: Uses subdirectory path detection
- **InfinityFree**: Uses clean domain URL
- **Production**: Uses clean domain URL

### Database Connection
- **Localhost**: Creates database if not exists, then connects
- **InfinityFree**: Connects directly to existing database
- **Production**: Connects directly to existing database

## 📧 InfinityFree Specific Settings

### Database Host
Usually: `sql312.infinityfree.com` (check your InfinityFree panel)

### Email Configuration
- Use a real email address (not Gmail for production)
- Get Brevo API key from [Brevo Dashboard](https://app.brevo.com/)

### File Paths
- Sessions: `logs/sessions/` (auto-created)
- Uploads: `assets/uploads/` (auto-created)
- Logs: `logs/error.log` (auto-created)

## 🧪 Testing on InfinityFree

### 1. Basic Functionality Test
Visit your domain and test:
- [ ] Homepage loads correctly
- [ ] Registration works
- [ ] Login works
- [ ] Admin panel accessible

### 2. Email Testing
Use the email test tools:
- `email-test-production.php` - Test with real data
- `email-diagnostic.php` - Debug email issues

### 3. Contact Form Test
- Submit a test message via contact form
- Check if admin receives it in contact messages
- Test reply functionality

### 4. Challenge System Test
- Create two test accounts
- Send a challenge from one profile to another
- Verify email notification is received

### 5. Maintenance Mode Test
- Enable maintenance mode in admin panel
- Verify all pages show maintenance screen
- Test that admin login still works
- Disable maintenance mode

## 🚨 Common Issues & Solutions

### Issue: Database Connection Failed
**Solution**: Check database credentials in `config/database.php`
- Verify database exists in InfinityFree panel
- Ensure user has proper permissions

### Issue: Emails Not Sending
**Solution**: Check email configuration in `config/email-config.php`
- Verify Brevo API key is valid
- Check if FROM email is verified in Brevo

### Issue: File Upload Errors
**Solution**: Check folder permissions
- Set `assets/uploads/` to 755
- Set `logs/` to 755

### Issue: Session Issues
**Solution**: Check session path permissions
- Ensure `logs/sessions/` exists and is writable
- Check PHP session configuration

### Issue: Maintenance Mode Stuck
**Solution**: Access admin control directly:
- `yourdomain.com/admin/maintenance-control.php`

## 🔄 Development to Production Workflow

### 1. Test Everything Locally
- All features working on XAMPP
- Database properly seeded
- Emails working with test credentials

### 2. Update Configuration
- Change database credentials for InfinityFree
- Update email settings for production
- Set proper file permissions

### 3. Deploy to InfinityFree
- Upload all files
- Run database setup if needed
- Test all functionality

### 4. Final Checks
- Test user registration/login
- Test admin panel
- Test email functionality
- Test maintenance mode

## 📞 Support Resources

### InfinityFree Documentation
- [InfinityFree Help Center](https://infinityfree.com/help)
- [InfinityFree Forums](https://infinityfree.com/forums)

### Database Management
- Use phpMyAdmin in InfinityFree control panel
- Or use external database tools

### File Management
- Use File Manager in InfinityFree control panel
- Or use FTP client for bulk uploads

## ✅ Pre-Deployment Checklist

- [ ] All files uploaded to InfinityFree
- [ ] Database created and configured
- [ ] Email settings updated
- [ ] File permissions set correctly
- [ ] Test all major functionality
- [ ] Maintenance mode tested
- [ ] Contact form tested
- [ ] Challenge system tested

## 🎯 Post-Deployment

Once deployed:
1. **Monitor** the application for errors
2. **Test** all features regularly
3. **Backup** database periodically
4. **Update** security patches as needed
5. **Check** email deliverability

---

**Note**: This configuration is specifically optimized for InfinityFree hosting while maintaining compatibility with local development environments.
