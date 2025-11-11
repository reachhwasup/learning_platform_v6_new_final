# RHEL 9.2 Installation Checklist
## Learning Platform v6 - Complete Dependency List

**Target OS:** Red Hat Enterprise Linux 9.2 (Plow)  
**Date:** November 11, 2025  
**Repository:** learning_platform_v6_final

---

## 📋 Pre-Installation Requirements

### System Information Needed
- [ ] Root/sudo access confirmed
- [ ] Server IP address: _________________
- [ ] Hostname: _________________
- [ ] Network gateway: _________________
- [ ] DNS servers: _________________

### Network Ports Required
- [ ] Port 22 (SSH) - Administration
- [ ] Port 80 (HTTP) - Web access
- [ ] Port 443 (HTTPS) - Secure web access
- [ ] Port 3306 (MySQL) - Internal only, NOT exposed

---

## 🔧 Core System Packages

### 1. Repository Configuration (Rocky Linux 9 as RHEL alternative)
```bash
# Download repository packages
curl -O https://dl.rockylinux.org/pub/rocky/9/BaseOS/x86_64/os/Packages/r/rocky-repos-9.2-1.el9.noarch.rpm
curl -O https://dl.rockylinux.org/pub/rocky/9/BaseOS/x86_64/os/Packages/r/rocky-release-9.2-1.el9.noarch.rpm
curl -O https://dl.rockylinux.org/pub/rocky/9/BaseOS/x86_64/os/Packages/r/rocky-gpg-keys-9.2-1.el9.noarch.rpm

# Install repositories
sudo rpm -ivh --nodeps --force rocky-gpg-keys-9.2-1.el9.noarch.rpm
sudo rpm -ivh --nodeps --force rocky-repos-9.2-1.el9.noarch.rpm
sudo rpm -ivh --nodeps --force rocky-release-9.2-1.el9.noarch.rpm
```

**Packages Installed:**
- [ ] rocky-gpg-keys-9.2-1.el9.noarch.rpm
- [ ] rocky-repos-9.2-1.el9.noarch.rpm
- [ ] rocky-release-9.2-1.el9.noarch.rpm

---

## 🌐 Web Server Stack

### 2. Apache HTTP Server
```bash
sudo dnf install -y httpd
```

**Package:** `httpd`  
**Service Name:** `httpd.service`  
**Config Location:** `/etc/httpd/conf/httpd.conf`  
**Document Root:** `/var/www/html`  
**Log Location:** `/var/log/httpd/`

**Installation Checklist:**
- [ ] httpd package installed
- [ ] Service started: `sudo systemctl start httpd`
- [ ] Service enabled: `sudo systemctl enable httpd`
- [ ] Service status verified: `sudo systemctl status httpd`
- [ ] Firewall configured: `sudo firewall-cmd --permanent --add-service=http`
- [ ] Firewall configured: `sudo firewall-cmd --permanent --add-service=https`
- [ ] Firewall reloaded: `sudo firewall-cmd --reload`

---

## 🐘 PHP Runtime & Extensions

### 3. PHP Core and Extensions
```bash
sudo dnf install -y php php-cli php-fpm
```

**Main Package:** `php` (version 8.x)  
**Service Name:** `php-fpm.service` (if using FPM)

**Installation Checklist:**
- [ ] php - Core PHP interpreter
- [ ] php-cli - Command line interface
- [ ] php-fpm - FastCGI Process Manager (optional)

### 4. PHP Database Extensions
```bash
sudo dnf install -y php-mysqlnd php-pdo
```

**Packages:**
- [ ] php-mysqlnd - MySQL Native Driver
- [ ] php-pdo - PHP Data Objects (usually included)

### 5. PHP Additional Extensions
```bash
sudo dnf install -y php-gd php-mbstring php-xml php-json php-zip php-curl php-intl
```

**Packages:**
- [ ] php-gd - Image processing (thumbnails, charts)
- [ ] php-mbstring - Multibyte string handling
- [ ] php-xml - XML processing
- [ ] php-json - JSON encoding/decoding
- [ ] php-zip - ZIP archive handling (for exports)
- [ ] php-curl - HTTP requests (optional)
- [ ] php-intl - Internationalization (optional)

**PHP Configuration:**
- [ ] Verify PHP version: `php -v`
- [ ] Check loaded extensions: `php -m`
- [ ] PHP config location: `/etc/php.ini`

---

## 🗄️ Database Server

### 6. MariaDB/MySQL Server
```bash
sudo dnf install -y mariadb-server mariadb
```

**Packages:**
- [ ] mariadb-server - Database server
- [ ] mariadb - Client tools

**Service Name:** `mariadb.service`  
**Config Location:** `/etc/my.cnf` or `/etc/my.cnf.d/`  
**Data Directory:** `/var/lib/mysql/`  
**Socket:** `/var/lib/mysql/mysql.sock`

**Installation Checklist:**
- [ ] MariaDB server installed
- [ ] Service started: `sudo systemctl start mariadb`
- [ ] Service enabled: `sudo systemctl enable mariadb`
- [ ] Service status verified: `sudo systemctl status mariadb`
- [ ] Secure installation run: `sudo mysql_secure_installation`
  - [ ] Root password set
  - [ ] Anonymous users removed
  - [ ] Remote root login disabled
  - [ ] Test database removed
  - [ ] Privileges reloaded

**Database Configuration:**
- [ ] Database created: `learning_platform`
- [ ] User created: `learning_user`
- [ ] Password set: (record securely)
- [ ] Privileges granted
- [ ] Character set: UTF8MB4
- [ ] Collation: utf8mb4_unicode_ci

---

## 🔧 Development & Version Control Tools

### 7. Git Version Control
```bash
sudo dnf install -y git
```

**Package:** `git`

**Installation Checklist:**
- [ ] Git installed
- [ ] Git version verified: `git --version`
- [ ] Git configured (optional):
  ```bash
  git config --global user.name "Your Name"
  git config --global user.email "your.email@bank.com"
  ```

### 8. Composer (PHP Dependency Manager)
```bash
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

**Binary Location:** `/usr/local/bin/composer`

**Installation Checklist:**
- [ ] Composer downloaded
- [ ] Composer installed globally
- [ ] Composer verified: `composer --version`

---

## 🔒 Security & System Tools

### 9. SELinux Tools
```bash
sudo dnf install -y policycoreutils-python-utils
```

**Packages:**
- [ ] policycoreutils-python-utils - SELinux management tools

**Commands Available:**
- `semanage` - SELinux policy management
- `restorecon` - Restore file security contexts
- `setsebool` - Set SELinux boolean values

**Installation Checklist:**
- [ ] SELinux tools installed
- [ ] SELinux status checked: `sestatus`
- [ ] SELinux mode: Enforcing/Permissive/Disabled

### 10. Firewall Configuration
**Service:** `firewalld` (pre-installed on RHEL 9)

**Installation Checklist:**
- [ ] Firewall status: `sudo systemctl status firewalld`
- [ ] Firewall enabled: `sudo systemctl enable firewalld`
- [ ] HTTP service added
- [ ] HTTPS service added
- [ ] SSH service verified (default)
- [ ] Firewall rules reloaded

---

## 📦 Additional Utility Packages

### 11. Archive & Download Tools
```bash
sudo dnf install -y unzip wget tar
```

**Packages:**
- [ ] unzip - Extract ZIP archives
- [ ] wget - Download files
- [ ] tar - Archive handling (usually pre-installed)

### 12. Text Editors (optional)
```bash
sudo dnf install -y nano vim
```

**Packages:**
- [ ] nano - Simple text editor
- [ ] vim - Advanced text editor

---

## 📊 PHP Libraries (via Composer)

### 13. PhpSpreadsheet
**Installed via:** Composer  
**Purpose:** Excel file generation and reading  
**Used for:** Report exports

```bash
cd /var/www/html/learning_platform
composer require phpoffice/phpspreadsheet
```

**Composer Dependencies (from composer.json):**
- [ ] phpoffice/phpspreadsheet - Excel export functionality
- [ ] maennchen/zipstream-php - Streaming ZIP archives
- [ ] markbaker/complex - Complex number calculations
- [ ] markbaker/matrix - Matrix calculations

**Installation Checklist:**
- [ ] composer.json exists
- [ ] Dependencies installed: `composer install --no-dev`
- [ ] vendor/ directory created
- [ ] autoload.php generated

---

## 🗂️ Application Structure

### 14. Directory Permissions

**Required Directories:**
```
/var/www/html/learning_platform/
├── uploads/
│   ├── profile_pictures/    (775)
│   ├── thumbnails/          (775)
│   └── videos/              (775)
├── includes/                (755)
├── api/                     (755)
├── admin/                   (755)
└── vendor/                  (755)
```

**Permission Checklist:**
- [ ] Application owner: `apache:apache`
- [ ] Base permissions: `755` (directories), `644` (files)
- [ ] Uploads directory: `775` (writable)
- [ ] Profile pictures: `775`
- [ ] Thumbnails: `775`
- [ ] Videos directory: `775`

**SELinux Contexts:**
- [ ] Uploads context: `httpd_sys_rw_content_t`
- [ ] Application context: `httpd_sys_content_t`

---

## 🌐 Network Services Summary

### Services to Start and Enable

| Service | Package | Command |
|---------|---------|---------|
| Apache | httpd | `sudo systemctl enable --now httpd` |
| MariaDB | mariadb-server | `sudo systemctl enable --now mariadb` |
| Firewall | firewalld | `sudo systemctl enable --now firewalld` |
| PHP-FPM | php-fpm | `sudo systemctl enable --now php-fpm` (optional) |

**Service Status Checklist:**
- [ ] `sudo systemctl status httpd` - Active (running)
- [ ] `sudo systemctl status mariadb` - Active (running)
- [ ] `sudo systemctl status firewalld` - Active (running)

---

## 🔐 SELinux Configuration

### Required SELinux Booleans
```bash
# Allow Apache to connect to database
sudo setsebool -P httpd_can_network_connect_db 1

# Allow Apache to send mail (optional)
sudo setsebool -P httpd_can_sendmail 1

# Allow Apache to make network connections (optional)
sudo setsebool -P httpd_can_network_connect 1
```

**SELinux Checklist:**
- [ ] httpd_can_network_connect_db - Enabled
- [ ] Uploads directory context set
- [ ] Context restored: `sudo restorecon -Rv /var/www/html/learning_platform`

---

## 📝 Configuration Files to Edit

### 1. Database Connection
**File:** `/var/www/html/learning_platform/includes/db_connect.php`

```php
$host = 'localhost';
$db = 'learning_platform';
$user = 'learning_user';
$pass = 'YourStrongPassword';
```

**Checklist:**
- [ ] Database host configured
- [ ] Database name configured
- [ ] Database user configured
- [ ] Database password configured
- [ ] Connection tested

### 2. Apache Virtual Host (optional)
**File:** `/etc/httpd/conf.d/learning_platform.conf`

```apache
<VirtualHost *:80>
    ServerName your-domain-or-ip
    DocumentRoot /var/www/html/learning_platform
    
    <Directory /var/www/html/learning_platform>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog /var/log/httpd/learning_platform_error.log
    CustomLog /var/log/httpd/learning_platform_access.log combined
</VirtualHost>
```

**Checklist:**
- [ ] Virtual host file created
- [ ] ServerName configured
- [ ] DocumentRoot set
- [ ] Directory permissions configured
- [ ] Apache restarted

### 3. PHP Configuration (optional tuning)
**File:** `/etc/php.ini`

```ini
upload_max_filesize = 512M
post_max_size = 512M
memory_limit = 256M
max_execution_time = 300
```

**Checklist:**
- [ ] Upload size limits adjusted (for video uploads)
- [ ] Memory limit set
- [ ] Execution time increased
- [ ] Apache restarted after changes

---

## 🗃️ Database Setup

### SQL Schema Import

**Checklist:**
- [ ] Database dump file obtained
- [ ] Schema imported: `mysql -u learning_user -p learning_platform < database.sql`
- [ ] Tables verified:
  - [ ] users
  - [ ] departments
  - [ ] modules
  - [ ] questions
  - [ ] final_assessments
  - [ ] user_progress
  - [ ] quiz_results
- [ ] Default admin user created
- [ ] Default departments added

---

## 🚀 Application Deployment

### Repository Clone
```bash
cd /var/www/html
sudo git clone https://github.com/reachhwasup/learning_platform_v6_final.git learning_platform
```

**Checklist:**
- [ ] Repository cloned successfully
- [ ] Branch verified: `git branch`
- [ ] Latest commit verified: `git log -1`

### File Permissions
```bash
sudo chown -R apache:apache /var/www/html/learning_platform
sudo chmod -R 755 /var/www/html/learning_platform
sudo chmod -R 775 /var/www/html/learning_platform/uploads
```

**Checklist:**
- [ ] Ownership set to apache:apache
- [ ] Base permissions applied
- [ ] Uploads directory writable

### Composer Dependencies
```bash
cd /var/www/html/learning_platform
sudo composer install --no-dev --optimize-autoloader
```

**Checklist:**
- [ ] Composer dependencies installed
- [ ] vendor/ directory created
- [ ] No dependency conflicts
- [ ] Autoloader optimized

---

## ✅ Verification & Testing

### Service Verification
```bash
# Check all services are running
sudo systemctl status httpd mariadb firewalld

# Check PHP version and modules
php -v
php -m | grep -E 'mysqli|gd|mbstring|xml|json|zip'

# Check database connection
mysql -u learning_user -p -e "SHOW DATABASES;"

# Check Apache configuration
sudo apachectl configtest
```

**Verification Checklist:**
- [ ] Apache responding on port 80
- [ ] MariaDB accepting connections
- [ ] PHP version 8.x confirmed
- [ ] All PHP modules loaded
- [ ] Database accessible
- [ ] Apache config syntax valid

### Application Testing
**Access URLs:**
- [ ] Main page: `http://your-server-ip/learning_platform/`
- [ ] Login page: `http://your-server-ip/learning_platform/login.php`
- [ ] Admin login: `http://your-server-ip/learning_platform/admin/login.php`

**Functional Tests:**
- [ ] Homepage loads without errors
- [ ] User can login
- [ ] Admin can login
- [ ] Dashboard displays correctly
- [ ] Module access works
- [ ] Video playback functional
- [ ] Quiz submission works
- [ ] Assessment system operational
- [ ] Profile upload works
- [ ] Admin CRUD operations work

### Log Verification
```bash
# Apache error logs
sudo tail -f /var/log/httpd/error_log

# Apache access logs
sudo tail -f /var/log/httpd/access_log

# MariaDB logs
sudo tail -f /var/log/mariadb/mariadb.log

# PHP errors (check php.ini for error_log location)
sudo tail -f /var/log/php-fpm/error.log
```

**Log Checklist:**
- [ ] No critical errors in Apache logs
- [ ] No PHP fatal errors
- [ ] No database connection errors
- [ ] Access logs showing requests

---

## 🔒 Security Hardening (Post-Installation)

### Apache Security
- [ ] Disable directory listing
- [ ] Hide Apache version: `ServerTokens Prod`
- [ ] Disable unused modules
- [ ] Configure mod_security (optional)

### PHP Security
- [ ] `display_errors = Off` in production
- [ ] `expose_php = Off`
- [ ] `disable_functions` for dangerous functions
- [ ] Enable `open_basedir` restriction

### Database Security
- [ ] Remove test database
- [ ] Disable remote root login
- [ ] Use strong passwords
- [ ] Regular backups configured

### Firewall Rules
- [ ] Only necessary ports open
- [ ] SSH restricted to admin IPs (optional)
- [ ] Rate limiting configured (optional)

---

## 📦 Complete Package List Summary

```bash
# Single command to install all packages
sudo dnf install -y \
  httpd \
  php php-cli php-fpm \
  php-mysqlnd php-pdo \
  php-gd php-mbstring php-xml php-json php-zip \
  mariadb-server mariadb \
  git \
  unzip wget \
  nano vim \
  policycoreutils-python-utils
```

**Total Packages:** ~20-25 packages (including dependencies)

---

## 🎯 Quick Installation Script

Save this as `install.sh`:

```bash
#!/bin/bash
# Quick installation script for Learning Platform v6

echo "🚀 Starting installation..."

# Update system
echo "📦 Updating system..."
sudo dnf clean all
sudo dnf update -y

# Install all packages
echo "📦 Installing packages..."
sudo dnf install -y httpd php php-cli php-mysqlnd php-gd php-mbstring \
  php-xml php-json php-zip mariadb-server mariadb git unzip wget \
  policycoreutils-python-utils

# Install Composer
echo "📦 Installing Composer..."
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Start services
echo "🔧 Starting services..."
sudo systemctl start httpd mariadb
sudo systemctl enable httpd mariadb

# Configure firewall
echo "🔥 Configuring firewall..."
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload

# SELinux configuration
echo "🔒 Configuring SELinux..."
sudo setsebool -P httpd_can_network_connect_db 1

echo "✅ Installation complete!"
echo "Next steps:"
echo "1. Run: sudo mysql_secure_installation"
echo "2. Create database and user"
echo "3. Clone repository"
echo "4. Configure database connection"
```

---

## 📋 Installation Order Summary

1. ✅ Configure repositories (Rocky Linux)
2. ✅ Install Apache web server
3. ✅ Install PHP and extensions
4. ✅ Install MariaDB database
5. ✅ Install Git and Composer
6. ✅ Install utility packages
7. ✅ Configure and secure MariaDB
8. ✅ Create database and user
9. ✅ Clone application repository
10. ✅ Install Composer dependencies
11. ✅ Set file permissions
12. ✅ Configure SELinux
13. ✅ Configure Apache virtual host
14. ✅ Configure application settings
15. ✅ Test and verify installation

---

## 📞 Support & Troubleshooting

### Common Issues

**Issue: Repository not found**
- Solution: Use Rocky Linux repositories as shown in Section 1

**Issue: SELinux blocking access**
- Solution: Check logs: `sudo ausearch -m avc -ts recent`
- Solution: Set proper contexts as shown in SELinux section

**Issue: Database connection failed**
- Solution: Verify MariaDB is running: `sudo systemctl status mariadb`
- Solution: Check credentials in db_connect.php

**Issue: Apache not starting**
- Solution: Check logs: `sudo journalctl -xe`
- Solution: Test config: `sudo apachectl configtest`

### Useful Commands

```bash
# Check all service status
sudo systemctl status httpd mariadb firewalld

# View PHP info
php -i | less

# Test database connection
mysql -u learning_user -p -e "SELECT VERSION();"

# Check Apache syntax
sudo apachectl -t

# View SELinux denials
sudo ausearch -m avc -ts today
```

---

**Installation Date:** _______________  
**Completed By:** _______________  
**Server IP:** _______________  
**Database Password:** _______________ (store securely!)

---

**End of Checklist**
