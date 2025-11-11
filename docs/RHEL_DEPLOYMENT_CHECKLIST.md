# RHEL Deployment Checklist - Learning Platform
## Quick Reference for Bank IT Team

**Version:** 6.0  
**Date:** October 28, 2025  
**Target:** 500 Users on RHEL 8/9

---

## 📋 Pre-Deployment Checklist

### 1. Hardware Requirements ✅

- [ ] **CPU:** 8 cores (Intel Xeon recommended)
- [ ] **RAM:** 16GB minimum (32GB ideal)
- [ ] **Storage:**
  - [ ] 120GB SSD for OS
  - [ ] 100GB SSD for database
  - [ ] 280GB+ for videos/files
- [ ] **Network:** 1Gbps Ethernet NIC

---

### 2. Network Configuration ✅

#### Static IP Configuration
- [ ] **IP Address:** _________________ (e.g., 192.168.10.100)
- [ ] **Subnet Mask:** _________________ (e.g., 255.255.255.0)
- [ ] **Gateway:** _________________ (e.g., 192.168.10.1)
- [ ] **DNS Primary:** _________________ (e.g., 192.168.10.2)
- [ ] **DNS Secondary:** _________________ (e.g., 192.168.10.3)
- [ ] **Hostname:** _________________ (e.g., learning.bank.local)

#### Bandwidth
- [ ] **Minimum:** 200 Mbps
- [ ] **Recommended:** 500 Mbps - 1 Gbps

---

### 3. Firewall Rules (Request from Network Team) ✅

#### Inbound Rules
```
Source: Bank internal network (e.g., 192.168.0.0/16)
Destination: Learning Server IP
Ports: 80 (HTTP), 443 (HTTPS)
Protocol: TCP
Action: ALLOW
```

#### SSH Access (Admin Only)
```
Source: IT Admin subnet (e.g., 192.168.1.0/24)
Destination: Learning Server IP
Port: 22
Protocol: TCP
Action: ALLOW
```

#### Outbound Rules
```
Source: Learning Server IP
Destination: Internet (RHEL repositories)
Ports: 80, 443
Protocol: TCP
Action: ALLOW (for updates)
```

---

### 4. DNS Configuration (Request from DNS Admin) ✅

#### A Record
- [ ] **Name:** learning
- [ ] **Type:** A
- [ ] **Value:** [Server IP Address]
- [ ] **TTL:** 3600

**Result:** `learning.bank.local` → Server IP

#### Optional CNAME
- [ ] **Name:** elearning
- [ ] **Type:** CNAME
- [ ] **Value:** learning.bank.local

---

### 5. SSL Certificate (Request from Certificate Authority) ✅

**Option 1: Internal CA (Recommended)**
- [ ] **Common Name:** learning.bank.local
- [ ] **Organization:** [Bank Name]
- [ ] **Key Size:** 2048-bit RSA
- [ ] **Validity:** 1-2 years
- [ ] **Files Needed:**
  - [ ] Certificate file (.crt)
  - [ ] Private key (.key)
  - [ ] CA bundle (.crt)

**Option 2: Self-Signed (Testing Only)**
- [ ] Generate during installation

---

### 6. Access Requirements ✅

- [ ] **SSH Access:** username/password or SSH key
- [ ] **Sudo/Root Access:** Required for installation
- [ ] **RHEL Subscription:** Active (for updates)

---

### 7. Files to Prepare ✅

- [ ] Application source code (ZIP or Git repository)
- [ ] SSL certificate files (if using internal CA)
- [ ] Video content files (MP4 format, H.264 codec)
- [ ] PDF learning materials
- [ ] Database backup (if migrating)

---

### 8. Information to Collect ✅

**Server Details:**
- [ ] Server IP Address: _________________
- [ ] Server Hostname: _________________
- [ ] SSH Username: _________________
- [ ] SSH Password/Key: _________________

**Database Configuration:**
- [ ] Database Name: `learning_platform_v6` (default)
- [ ] Database User: `learning_platform_user` (default)
- [ ] Database Password: _________________ (generate strong password)

**Administrator Account:**
- [ ] Admin Username: `admin.gov` (default)
- [ ] Admin Email: _________________
- [ ] Admin Password: _________________ (will set during first login)

**SMTP Settings (Optional - for email notifications):**
- [ ] SMTP Server: _________________
- [ ] SMTP Port: _________ (25, 587, or 465)
- [ ] SMTP Username: _________________
- [ ] SMTP Password: _________________

---

## 🚀 Installation Steps Summary

### Step 1: System Preparation (30 minutes)
```bash
# Update system
dnf update -y

# Install repositories
dnf install -y epel-release
dnf config-manager --set-enabled powertools  # RHEL 8
# or
dnf config-manager --set-enabled crb  # RHEL 9
```

### Step 2: Install Web Server (15 minutes)
```bash
# Install Apache
dnf install -y httpd mod_ssl

# Start and enable
systemctl start httpd
systemctl enable httpd

# Configure firewall
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload
```

### Step 3: Install PHP (20 minutes)
```bash
# Install Remi repository for PHP 8.x
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Enable PHP 8.1
dnf module reset php
dnf module enable php:remi-8.1
dnf install -y php php-mysqlnd php-gd php-mbstring php-xml \
    php-json php-intl php-zip php-opcache

# Restart Apache
systemctl restart httpd
```

### Step 4: Install Database (25 minutes)
```bash
# Install MariaDB
dnf install -y mariadb-server mariadb

# Start and enable
systemctl start mariadb
systemctl enable mariadb

# Secure installation
mysql_secure_installation

# Create database and user
mysql -u root -p
```

```sql
CREATE DATABASE learning_platform_v6 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'learning_platform_user'@'localhost' IDENTIFIED BY 'YourSecurePassword';
GRANT ALL PRIVILEGES ON learning_platform_v6.* TO 'learning_platform_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 5: Deploy Application (30 minutes)
```bash
# Create directory
mkdir -p /var/www/learning_platform

# Upload files (use SCP, SFTP, or Git)
# Extract if ZIP
unzip learning_platform_v6.zip -d /var/www/learning_platform/

# Set permissions
chown -R apache:apache /var/www/learning_platform
chmod -R 755 /var/www/learning_platform
chmod -R 775 /var/www/learning_platform/uploads

# Configure SELinux
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/learning_platform/uploads(/.*)?"
restorecon -Rv /var/www/learning_platform/

# Import database
mysql -u learning_platform_user -p learning_platform_v6 < /var/www/learning_platform/database.sql
```

### Step 6: Configure Apache Virtual Host (15 minutes)
```bash
# Create virtual host configuration
nano /etc/httpd/conf.d/learning_platform.conf
```

Add:
```apache
<VirtualHost *:80>
    ServerName learning.bank.local
    DocumentRoot /var/www/learning_platform
    
    <Directory /var/www/learning_platform>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog /var/log/httpd/learning_error.log
    CustomLog /var/log/httpd/learning_access.log combined
    
    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName learning.bank.local
    DocumentRoot /var/www/learning_platform
    
    SSLEngine on
    SSLCertificateFile /etc/pki/tls/certs/learning.crt
    SSLCertificateKeyFile /etc/pki/tls/private/learning.key
    
    <Directory /var/www/learning_platform>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog /var/log/httpd/learning_ssl_error.log
    CustomLog /var/log/httpd/learning_ssl_access.log combined
</VirtualHost>
```

```bash
# Restart Apache
systemctl restart httpd
```

### Step 7: Configure SELinux (10 minutes)
```bash
# Allow Apache network connections (if needed)
setsebool -P httpd_can_network_connect on

# Allow Apache to send emails (if needed)
setsebool -P httpd_can_sendmail on

# Verify SELinux contexts
ls -Z /var/www/learning_platform/
```

### Step 8: Testing (15 minutes)
```bash
# Test from server
curl http://localhost
curl https://localhost -k

# From workstation browser
https://learning.bank.local
```

**Total Installation Time:** ~2.5 - 3 hours

---

## ✅ Post-Deployment Checklist

### Security Configuration
- [ ] Firewall configured (ports 80, 443 open)
- [ ] SELinux enabled and configured
- [ ] SSL certificate installed
- [ ] HTTPS redirect working
- [ ] Default admin password changed
- [ ] SSH key-based authentication (optional)
- [ ] Fail2ban installed (optional security)

### Performance Verification
- [ ] Website loads in <2 seconds
- [ ] Video playback works smoothly
- [ ] PDF downloads work
- [ ] File uploads work
- [ ] Database queries respond quickly

### Backup Configuration
- [ ] Daily database backup scheduled (cron)
- [ ] Weekly file backup scheduled
- [ ] Backup retention policy (30 days recommended)
- [ ] Backup storage location configured
- [ ] Test restore procedure

### Monitoring Setup
- [ ] Log rotation configured
- [ ] Disk space monitoring
- [ ] Service monitoring (httpd, mariadb)
- [ ] Performance monitoring (optional)
- [ ] Email alerts configured (optional)

### Documentation
- [ ] Server credentials documented (secure location)
- [ ] Network configuration documented
- [ ] Database credentials documented
- [ ] SSL certificate expiry noted
- [ ] Backup procedures documented
- [ ] Disaster recovery plan created

---

## 🔧 Quick Commands Reference

### Service Management
```bash
# Apache
systemctl start httpd
systemctl stop httpd
systemctl restart httpd
systemctl status httpd

# MariaDB
systemctl start mariadb
systemctl stop mariadb
systemctl restart mariadb
systemctl status mariadb

# Firewall
firewall-cmd --list-all
firewall-cmd --reload
```

### Logs Location
```bash
# Apache logs
tail -f /var/log/httpd/learning_error.log
tail -f /var/log/httpd/learning_access.log

# MariaDB logs
tail -f /var/log/mariadb/mariadb.log

# System logs
journalctl -u httpd -f
journalctl -u mariadb -f
```

### Database Access
```bash
# Connect to database
mysql -u learning_platform_user -p learning_platform_v6

# Backup database
mysqldump -u learning_platform_user -p learning_platform_v6 > backup_$(date +%Y%m%d).sql

# Restore database
mysql -u learning_platform_user -p learning_platform_v6 < backup_20251028.sql
```

### File Permissions
```bash
# Fix permissions
chown -R apache:apache /var/www/learning_platform
chmod -R 755 /var/www/learning_platform
chmod -R 775 /var/www/learning_platform/uploads

# Fix SELinux contexts
restorecon -Rv /var/www/learning_platform/
```

---

## 📞 Support Contact

**For deployment assistance:**
- Refer to: `docs/RHEL_DEPLOYMENT_GUIDE.md` (complete guide)
- Troubleshooting: See guide Step 9
- OS Comparison: `docs/OS_COMPARISON_GUIDE.md`

**Network team coordination:**
- Firewall rules (ports 80, 443, 22)
- DNS A record configuration
- Bandwidth allocation (1Gbps recommended)

**Security team coordination:**
- SSL certificate request
- Security audit/approval
- Compliance verification

---

## 🎯 Success Criteria

**Deployment is successful when:**
- ✅ Website accessible via https://learning.bank.local
- ✅ Users can login with credentials
- ✅ Videos play without buffering
- ✅ PDF materials download correctly
- ✅ File uploads work (profile pictures, assignments)
- ✅ Admin panel accessible and functional
- ✅ No SSL certificate warnings
- ✅ Page load time <2 seconds
- ✅ System handles 150-200 concurrent users smoothly
- ✅ Daily backups running automatically

---

**Document Version:** 1.0  
**Last Updated:** October 28, 2025  
**Next Review:** After initial deployment
