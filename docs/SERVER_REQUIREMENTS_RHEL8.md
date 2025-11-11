# Server Requirements & Deployment Guide
## Information Security Learning Platform v6
### Red Hat Enterprise Linux 8 Deployment

**Date:** October 31, 2025  
**Platform:** learning_platform_v6  
**Target Environment:** RHEL 8.x  
**Capacity:** 500 users, 100+ concurrent, 6 modules

---

## 📋 Table of Contents

1. [Hardware Requirements](#hardware-requirements)
2. [HDD Partition Scheme](#hdd-partition-scheme)
3. [Operating System](#operating-system)
4. [Software Stack](#software-stack)
5. [Installation Steps](#installation-steps)
6. [Configuration](#configuration)
7. [Security Setup](#security-setup)
8. [Performance Tuning](#performance-tuning)
9. [Backup Strategy](#backup-strategy)
10. [Monitoring](#monitoring)

---

## 💻 Hardware Requirements

### Recommended Specification (500 Users, 6 Modules)

```
┌─────────────────────────────────────────────────────┐
│  CPU:     6-8 cores @ 2.5GHz or higher              │
│           Intel Xeon E-2288G / AMD Ryzen 7          │
│                                                     │
│  RAM:     24GB DDR4 (32GB recommended)              │
│           2666MHz or higher                         │
│           ECC memory recommended for production     │
│                                                     │
│  HDD:     256GB minimum (500GB recommended)         │
│           7200 RPM HDD or SSD                       │
│           SATA III or NVMe interface                │
│                                                     │
│  Network: 1Gbps Ethernet (minimum)                  │
│           10Gbps recommended for video streaming    │
│                                                     │
│  RAID:    Optional but recommended (RAID 1/10)      │
└─────────────────────────────────────────────────────┘
```

### Performance Capacity

```yaml
With 6 cores / 24GB RAM / 256GB HDD:
  Total Users: 500-600
  Concurrent Users: 100-120
  Video Modules: 6 (current) - expandable to 20+
  Database Size: Up to 50GB
  Video Storage: ~1-2GB (6 modules)
  Page Load Time: < 2 seconds
  Video Streaming: Smooth for 60+ simultaneous streams
```

### Minimum Specification (Budget Option)

```
CPU: 6 cores @ 2.5GHz
RAM: 16GB DDR4
HDD: 256GB
Capacity: 300-400 users, 60-80 concurrent
```

---

## 💾 HDD Partition Scheme

### Option 1: Single Disk (256GB) - Standard Setup

```bash
Device: /dev/sda (256GB HDD)

┌──────────────────────────────────────────────────────────────┐
│ Partition  │ Mount Point      │ Size  │ Type  │ Filesystem  │
├──────────────────────────────────────────────────────────────┤
│ /dev/sda1  │ /boot/efi        │ 1GB   │ EFI   │ FAT32       │
│ /dev/sda2  │ /boot            │ 1GB   │ Boot  │ ext4        │
│ /dev/sda3  │ /                │ 80GB  │ Root  │ ext4        │
│ /dev/sda4  │ (LVM)            │ 174GB │ LVM   │ -           │
│   ├─ lv_mysql │ /var/lib/mysql │ 60GB  │ LV    │ ext4        │
│   ├─ lv_www   │ /var/www       │ 90GB  │ LV    │ ext4        │
│   └─ lv_backup│ /backup        │ 24GB  │ LV    │ ext4        │
└──────────────────────────────────────────────────────────────┘
```

### Detailed Partition Breakdown

#### **Partition 1: EFI System Partition**
```
Mount: /boot/efi
Size: 1GB
Type: FAT32
Purpose: UEFI boot files
Flags: boot, esp
Usage: ~200MB (plenty of space)
```

#### **Partition 2: Boot Partition**
```
Mount: /boot
Size: 1GB
Type: ext4
Purpose: Kernel and boot files
Contains:
  ├─ vmlinuz-* (kernel images)
  ├─ initramfs-* (initial RAM filesystem)
  └─ grub2/ (bootloader configuration)
Usage: ~300-500MB
```

#### **Partition 3: Root Partition**
```
Mount: /
Size: 80GB
Type: ext4
Purpose: Operating system and applications
Contains:
  ├─ /etc (configuration files): ~50MB
  ├─ /usr (system programs): 10-15GB
  ├─ /opt (optional software): 2-5GB
  ├─ /tmp (temporary files): 2-5GB
  ├─ /var/log (system logs): 10-20GB
  ├─ /var/cache (package cache): 5-10GB
  └─ /home (user directories): 5-10GB
Free space: ~25GB (for growth)
```

#### **LVM Physical Volume: Data Storage**

**Logical Volume 1: MySQL Database**
```
Mount: /var/lib/mysql
Size: 60GB
Type: ext4
Purpose: MySQL/MariaDB database storage
Contains:
  ├─ Database files (.ibd, .frm): 20-30GB
  ├─ Indexes: 10-15GB
  ├─ Binary logs: 5-10GB
  ├─ Transaction logs (ib_logfile): 2GB
  └─ Error logs: 1-2GB
Usage (current): ~5-10GB
Growth capacity: Up to 60GB (500 users)
```

**Logical Volume 2: Web Files**
```
Mount: /var/www
Size: 90GB
Type: ext4
Purpose: Web application and uploads
Structure:
  /var/www/html/
  ├─ Application files: 200MB
  │   ├─ PHP code
  │   ├─ CSS/JS assets
  │   └─ Vendor libraries
  ├─ uploads/
  │   ├─ videos/: 1GB (6 modules × 150MB)
  │   ├─ materials/: 100MB (PDFs, documents)
  │   ├─ posters/: 100MB (images)
  │   ├─ thumbnails/: 50MB
  │   └─ profile_pictures/: 50MB
  └─ Free space: 87GB
Usage (current): ~2-3GB
Free for expansion: Can add 50+ more modules
```

**Logical Volume 3: Backups**
```
Mount: /backup
Size: 24GB
Type: ext4
Purpose: Database and file backups
Structure:
  /backup/
  ├─ mysql/
  │   ├─ daily/: 7 days × 2GB = 14GB
  │   └─ weekly/: 4 weeks × 2GB = 8GB
  ├─ files/
  │   └─ weekly/: 2GB
  └─ Free space: ~10GB
Retention: 7 daily + 4 weekly backups
```

### Partition Creation Commands

```bash
# Create partitions using fdisk or parted
parted /dev/sda mklabel gpt

# EFI partition
parted /dev/sda mkpart EFI fat32 1MiB 1025MiB
parted /dev/sda set 1 esp on

# Boot partition
parted /dev/sda mkpart boot ext4 1025MiB 2049MiB

# Root partition
parted /dev/sda mkpart root ext4 2049MiB 81969MiB

# LVM partition
parted /dev/sda mkpart lvm ext4 81969MiB 100%
parted /dev/sda set 4 lvm on

# Create filesystems
mkfs.vfat -F32 /dev/sda1
mkfs.ext4 /dev/sda2
mkfs.ext4 /dev/sda3

# Setup LVM
pvcreate /dev/sda4
vgcreate vg_data /dev/sda4
lvcreate -L 60G -n lv_mysql vg_data
lvcreate -L 90G -n lv_www vg_data
lvcreate -L 24G -n lv_backup vg_data

# Format logical volumes
mkfs.ext4 /dev/vg_data/lv_mysql
mkfs.ext4 /dev/vg_data/lv_www
mkfs.ext4 /dev/vg_data/lv_backup
```

### /etc/fstab Configuration

```bash
# /etc/fstab entries
UUID=XXXX-XXXX  /boot/efi        vfat    defaults        0 2
UUID=XXXX-XXXX  /boot            ext4    defaults        0 2
UUID=XXXX-XXXX  /                ext4    defaults        1 1
/dev/mapper/vg_data-lv_mysql  /var/lib/mysql  ext4  defaults,noatime  0 2
/dev/mapper/vg_data-lv_www    /var/www        ext4  defaults,noatime  0 2
/dev/mapper/vg_data-lv_backup /backup         ext4  defaults,noatime  0 2
```

### Option 2: Hybrid Setup (SSD + HDD)

```bash
Disk 1: /dev/sda (120GB SSD) - Performance
├─ /boot/efi: 1GB
├─ /boot: 1GB
├─ /: 60GB (OS)
├─ /var/lib/mysql: 50GB (Database - needs speed)
└─ swap: 8GB

Disk 2: /dev/sdb (256GB HDD) - Storage
├─ /var/www: 150GB (Web files, videos)
└─ /backup: 106GB (Backups)

Benefits:
✓ Fast OS boot and responses (SSD)
✓ Fast database queries (SSD)
✓ Cheaper bulk storage for videos (HDD)
✓ Best performance/cost ratio
```

---

## 🖥️ Operating System

### Red Hat Enterprise Linux 8

```yaml
Operating System: Red Hat Enterprise Linux (RHEL)
Version: RHEL 8.8 or RHEL 8.9 (Latest)
Kernel: 4.18.0-477 or newer
Architecture: x86_64 (64-bit)

Download:
  URL: https://access.redhat.com/downloads
  ISO: rhel-8.9-x86_64-dvd.iso (~10GB)
  Checksum: Verify SHA256 after download

Installation Type:
  ├─ Server with GUI (Recommended for first deployment)
  └─ Minimal Install (For experienced administrators)

License:
  ├─ Development: Free (up to 16 systems)
  ├─ Production: Requires subscription (~$350-800/year)
  └─ Register: subscription-manager register

Support:
  ├─ Red Hat Customer Portal
  ├─ Official documentation
  └─ Security updates (10 years)
```

### Alternative: Rocky Linux 8 (Free RHEL Clone)

```yaml
Operating System: Rocky Linux
Version: Rocky Linux 8.8 or 8.9
Kernel: 4.18.0-477
Architecture: x86_64

Download:
  URL: https://rockylinux.org/download
  ISO: Rocky-8.9-x86_64-minimal.iso (~1.8GB)
  
License: Free (GPL)
Support: Community forums, documentation

Why Rocky Linux?
  ✅ 100% bug-for-bug compatible with RHEL
  ✅ Free forever (no licensing costs)
  ✅ Enterprise-grade stability
  ✅ Recommended for production use
  ✅ Binary compatible with RHEL packages
```

### Installation Options

```
Option 1: Graphical Installation (Recommended)
├─ Boot from ISO
├─ Select "Install Red Hat Enterprise Linux 8"
├─ Language: English (United States)
├─ Installation Destination: Custom partitioning
├─ Software Selection: Server with GUI
├─ Network: Configure static IP
└─ Root password + Create user account

Option 2: Minimal Installation (Advanced)
├─ Boot from ISO
├─ Select "Install in basic graphics mode"
├─ Software Selection: Minimal Install
├─ Add required packages later
└─ Lighter system footprint
```

---

## 📦 Software Stack

### Required Software Versions

#### 1. Apache HTTP Server

```yaml
Package: httpd
Version: Apache 2.4.37 (RHEL 8 default) or 2.4.57
Module: mod_ssl (for HTTPS)

Installation:
  dnf install httpd mod_ssl -y

Configuration Files:
  ├─ Main config: /etc/httpd/conf/httpd.conf
  ├─ SSL config: /etc/httpd/conf.d/ssl.conf
  ├─ Virtual hosts: /etc/httpd/conf.d/*.conf
  └─ Modules: /etc/httpd/conf.modules.d/

Service Management:
  systemctl enable httpd
  systemctl start httpd
  systemctl status httpd

Default Document Root: /var/www/html
Default Log Location: /var/log/httpd/
```

#### 2. PHP

```yaml
Package: php, php-fpm
Version: PHP 8.0.x (RHEL 8 AppStream)
Alternative: PHP 8.1/8.2 from Remi repository

Installation (PHP 8.0):
  dnf module enable php:8.0 -y
  dnf install php php-fpm php-mysqlnd php-gd php-xml \
              php-mbstring php-json php-zip php-curl \
              php-intl php-opcache -y

Installation (PHP 8.2 from Remi):
  dnf install https://rpms.remirepo.net/enterprise/remi-release-8.rpm -y
  dnf module reset php -y
  dnf module enable php:remi-8.2 -y
  dnf install php php-fpm php-mysqlnd php-gd php-xml \
              php-mbstring php-json php-zip php-curl \
              php-intl php-opcache -y

Required Extensions:
  ├─ php-mysqlnd (MySQL/MariaDB PDO driver)
  ├─ php-gd (Image processing for thumbnails)
  ├─ php-xml (XML parsing)
  ├─ php-mbstring (Multi-byte string support)
  ├─ php-json (JSON encoding/decoding)
  ├─ php-zip (ZIP file handling - PHPSpreadsheet)
  ├─ php-curl (HTTP requests, API calls)
  ├─ php-intl (Internationalization)
  └─ php-opcache (Performance optimization)

Configuration Files:
  ├─ PHP config: /etc/php.ini
  ├─ FPM config: /etc/php-fpm.conf
  ├─ FPM pool: /etc/php-fpm.d/www.conf
  └─ PHP modules: /etc/php.d/

Service Management:
  systemctl enable php-fpm
  systemctl start php-fpm
  systemctl status php-fpm

Default Socket: /run/php-fpm/www.sock
```

#### 3. MySQL / MariaDB

```yaml
Option 1: MariaDB (Recommended for RHEL 8)
Package: mariadb-server
Version: MariaDB 10.3.x (RHEL 8 default) or 10.5+

Installation:
  dnf install mariadb-server -y

Option 2: MySQL Community Server
Version: MySQL 8.0.x

Installation:
  dnf install https://dev.mysql.com/get/mysql80-community-release-el8-5.noarch.rpm
  dnf install mysql-server -y

Configuration Files:
  ├─ Main config: /etc/my.cnf
  ├─ Include dir: /etc/my.cnf.d/
  └─ Data directory: /var/lib/mysql

Service Management:
  systemctl enable mariadb  # or mysqld
  systemctl start mariadb
  systemctl status mariadb

Initial Security Setup:
  mysql_secure_installation
  
  Prompts:
  ├─ Set root password: YES (use strong password)
  ├─ Remove anonymous users: YES
  ├─ Disallow root login remotely: YES
  ├─ Remove test database: YES
  └─ Reload privilege tables: YES

Default Socket: /var/lib/mysql/mysql.sock
Default Port: 3306
```

#### 4. Composer (PHP Dependency Manager)

```yaml
Purpose: Install PHPSpreadsheet and other PHP dependencies
Version: Composer 2.6.x or later

Installation:
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php composer-setup.php
  mv composer.phar /usr/local/bin/composer
  chmod +x /usr/local/bin/composer
  php -r "unlink('composer-setup.php');"

Verify Installation:
  composer --version

Usage:
  cd /var/www/html
  composer install  # Install dependencies from composer.json
  composer update   # Update dependencies
```

#### 5. Additional Required Software

```yaml
Git (Version Control):
  Package: git
  Version: 2.39.x or later
  Install: dnf install git -y
  Purpose: Code deployment, version control

Certbot (SSL Certificates):
  Package: certbot python3-certbot-apache
  Version: 2.x
  Install: dnf install certbot python3-certbot-apache -y
  Purpose: Free SSL certificates from Let's Encrypt

Firewalld (Firewall):
  Package: firewalld
  Version: Built-in RHEL 8
  Install: dnf install firewalld -y
  Purpose: Network security, port management

SELinux Tools:
  Package: policycoreutils-python-utils
  Install: dnf install policycoreutils-python-utils -y
  Purpose: SELinux policy management

System Utilities:
  dnf install vim wget curl unzip htop ncdu -y
  
  ├─ vim: Text editor
  ├─ wget/curl: Download tools
  ├─ unzip: Archive extraction
  ├─ htop: Process monitoring
  └─ ncdu: Disk usage analyzer
```

---

## 🚀 Installation Steps

### Pre-Installation Checklist

```
□ RHEL 8 ISO downloaded and verified
□ Installation media created (USB/DVD)
□ Server hardware ready (6 cores, 24GB RAM, 256GB HDD)
□ Network configuration planned (static IP)
□ Root password decided (strong, documented)
□ Partition layout reviewed (see above)
□ Backup of existing data (if upgrading)
```

### Step 1: Base System Installation

```bash
# 1. Boot from RHEL 8 installation media
# 2. Select "Install Red Hat Enterprise Linux 8"
# 3. Configure installation settings (language, keyboard, timezone)

# After OS installation, update the system
dnf update -y

# Install EPEL repository (Extra Packages for Enterprise Linux)
dnf install epel-release -y

# Install development tools
dnf groupinstall "Development Tools" -y

# Reboot if kernel was updated
reboot
```

### Step 2: Install Web Server Stack

```bash
# Install Apache HTTP Server
dnf install httpd mod_ssl -y

# Enable PHP 8.0 module (or 8.2 from Remi)
dnf module list php
dnf module enable php:8.0 -y

# Install PHP and required extensions
dnf install php php-fpm php-mysqlnd php-gd php-xml \
            php-mbstring php-json php-zip php-curl \
            php-intl php-opcache -y

# Install MariaDB server
dnf install mariadb-server -y

# Enable and start services
systemctl enable httpd
systemctl enable php-fpm
systemctl enable mariadb

systemctl start httpd
systemctl start php-fpm
systemctl start mariadb

# Verify services are running
systemctl status httpd
systemctl status php-fpm
systemctl status mariadb
```

### Step 3: Install Additional Tools

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
composer --version

# Install Git
dnf install git -y
git --version

# Install SSL certificate tools
dnf install certbot python3-certbot-apache -y

# Install system monitoring tools
dnf install vim wget curl unzip htop ncdu -y

# Install SELinux management tools
dnf install policycoreutils-python-utils -y
```

### Step 4: Configure Firewall

```bash
# Enable and start firewall
systemctl enable firewalld
systemctl start firewalld

# Allow HTTP and HTTPS traffic
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https

# Allow SSH (if not already allowed)
firewall-cmd --permanent --add-service=ssh

# Optional: Allow MySQL remote access (only if needed)
# firewall-cmd --permanent --add-service=mysql

# Reload firewall to apply changes
firewall-cmd --reload

# Verify firewall rules
firewall-cmd --list-all
```

### Step 5: Secure MySQL/MariaDB

```bash
# Run MySQL security script
mysql_secure_installation

# Answer the prompts:
# 1. Enter current password for root: [Press Enter - no password yet]
# 2. Set root password? [Y/n]: Y
#    New password: [Enter strong password]
#    Re-enter new password: [Confirm password]
# 3. Remove anonymous users? [Y/n]: Y
# 4. Disallow root login remotely? [Y/n]: Y
# 5. Remove test database and access to it? [Y/n]: Y
# 6. Reload privilege tables now? [Y/n]: Y

# Test MySQL login
mysql -u root -p
# Enter password and verify access
# Type 'exit' to quit MySQL
```

### Step 6: Create Database and User

```bash
# Login to MySQL
mysql -u root -p

# Create database for learning platform
CREATE DATABASE learning_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create database user
CREATE USER 'lms_user'@'localhost' IDENTIFIED BY 'your_strong_password_here';

# Grant privileges
GRANT ALL PRIVILEGES ON learning_platform.* TO 'lms_user'@'localhost';

# Flush privileges
FLUSH PRIVILEGES;

# Verify
SHOW DATABASES;
SELECT User, Host FROM mysql.user WHERE User='lms_user';

# Exit MySQL
EXIT;
```

---

## ⚙️ Configuration

### PHP Configuration (/etc/php.ini)

```ini
[PHP]
; Performance & Memory
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
max_input_vars = 3000

; File Uploads (for video and material uploads)
file_uploads = On
upload_max_filesize = 500M
post_max_size = 500M
max_file_uploads = 20

; Session Management
session.save_handler = files
session.save_path = "/var/lib/php/session"
session.gc_maxlifetime = 3600
session.cookie_httponly = 1
session.cookie_secure = 0  ; Set to 1 when using HTTPS
session.use_strict_mode = 1

; Timezone (adjust for your location)
date.timezone = Asia/Vientiane  ; Or America/New_York, Europe/London, etc.

; Error Reporting (Production Settings)
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/log/php_errors.log

; Security Settings
expose_php = Off
allow_url_fopen = On
allow_url_include = Off
disable_functions = exec,passthru,shell_exec,system,proc_open,popen

; OPcache (Performance)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

### PHP-FPM Configuration (/etc/php-fpm.d/www.conf)

```ini
[www]
; User/Group
user = apache
group = apache

; Socket Configuration
listen = /run/php-fpm/www.sock
listen.owner = apache
listen.group = apache
listen.mode = 0660

; Process Manager Settings (for 24GB RAM)
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 10
pm.max_spare_servers = 20
pm.max_requests = 1000
pm.process_idle_timeout = 30s

; For 16GB RAM, use these instead:
; pm.max_children = 30
; pm.start_servers = 5
; pm.min_spare_servers = 5
; pm.max_spare_servers = 10

; Logging
php_admin_value[error_log] = /var/log/php-fpm/www-error.log
php_admin_flag[log_errors] = on

; Session
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/session

; Security
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
```

### MySQL/MariaDB Configuration (/etc/my.cnf.d/server.cnf)

```ini
[mysqld]
# Basic Settings
datadir = /var/lib/mysql
socket = /var/lib/mysql/mysql.sock
log-error = /var/log/mariadb/mariadb.log
pid-file = /run/mariadb/mariadb.pid

# Character Set (UTF-8 support)
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# InnoDB Settings (for 24GB RAM)
innodb_buffer_pool_size = 8G
innodb_log_file_size = 512M
innodb_log_buffer_size = 256M
innodb_file_per_table = 1
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_buffer_pool_instances = 8

# For 16GB RAM, use:
# innodb_buffer_pool_size = 6G
# innodb_log_file_size = 384M

# Connection Settings
max_connections = 200
max_user_connections = 180
thread_cache_size = 64
wait_timeout = 600
interactive_timeout = 600

# Query Cache
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 2M

# Table Settings
table_open_cache = 2048
table_definition_cache = 1024

# Temporary Tables
tmp_table_size = 512M
max_heap_table_size = 512M

# Buffer Settings
key_buffer_size = 256M
read_buffer_size = 2M
read_rnd_buffer_size = 4M
sort_buffer_size = 4M
join_buffer_size = 4M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mariadb/slow-query.log
long_query_time = 2
log_queries_not_using_indexes = 1

# Binary Logging (for backups and replication)
log_bin = /var/lib/mysql/mysql-bin
expire_logs_days = 7
max_binlog_size = 100M
binlog_format = ROW

# Performance Schema (monitoring)
performance_schema = ON

[client]
default-character-set = utf8mb4
socket = /var/lib/mysql/mysql.sock

[mysql]
default-character-set = utf8mb4
```

### Apache Configuration (/etc/httpd/conf/httpd.conf)

```apache
ServerRoot "/etc/httpd"
Listen 80

# Load required modules
Include conf.modules.d/*.conf

# User and Group
User apache
Group apache

# Admin email
ServerAdmin admin@your-domain.com

# Security Headers
ServerTokens Prod
ServerSignature Off
TraceEnable Off

# Performance Settings (for 6 cores / 24GB RAM)
<IfModule mpm_prefork_module>
    StartServers             8
    MinSpareServers          10
    MaxSpareServers          20
    MaxRequestWorkers        100
    MaxConnectionsPerChild   3000
</IfModule>

# For 16GB RAM:
# MaxRequestWorkers 75

# Directory Security
<Directory />
    AllowOverride none
    Require all denied
</Directory>

# Document Root
DocumentRoot "/var/www/html"

<Directory "/var/www/html">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# DirectoryIndex
<IfModule dir_module>
    DirectoryIndex index.php index.html
</IfModule>

# Deny access to .ht files
<Files ".ht*">
    Require all denied
</Files>

# PHP Handler
<FilesMatch \.php$>
    SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
</FilesMatch>

# Logging
ErrorLog "logs/error_log"
LogLevel warn

<IfModule log_config_module>
    LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
    LogFormat "%h %l %u %t \"%r\" %>s %b" common
    CustomLog "logs/access_log" combined
</IfModule>

# Include additional configurations
IncludeOptional conf.d/*.conf
```

### Virtual Host Configuration (/etc/httpd/conf.d/learning-platform.conf)

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/html
    
    # Logging
    ErrorLog /var/log/httpd/learning_platform_error.log
    CustomLog /var/log/httpd/learning_platform_access.log combined
    
    # Document Root Permissions
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks -MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    
    # Uploads Directory (No script execution)
    <Directory /var/www/html/uploads>
        Options -Indexes -ExecCGI
        AllowOverride None
        Require all granted
        
        # Prevent PHP execution in uploads
        <FilesMatch "\.php$">
            Require all denied
        </FilesMatch>
    </Directory>
    
    # Admin Directory (Optional: IP restriction)
    <Directory /var/www/html/admin>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Uncomment to restrict to specific IPs
        # Require ip 192.168.1.0/24
    </Directory>
    
    # API Directory
    <Directory /var/www/html/api>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
    </IfModule>
    
    # Browser Caching
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType image/jpg "access plus 1 year"
        ExpiresByType image/jpeg "access plus 1 year"
        ExpiresByType image/gif "access plus 1 year"
        ExpiresByType image/png "access plus 1 year"
        ExpiresByType text/css "access plus 1 month"
        ExpiresByType application/javascript "access plus 1 month"
        ExpiresByType video/mp4 "access plus 1 year"
    </IfModule>
</VirtualHost>

# SSL Configuration (after certbot setup)
# <VirtualHost *:443>
#     ServerName your-domain.com
#     DocumentRoot /var/www/html
#     
#     SSLEngine on
#     SSLCertificateFile /etc/letsencrypt/live/your-domain.com/cert.pem
#     SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem
#     SSLCertificateChainFile /etc/letsencrypt/live/your-domain.com/chain.pem
#     
#     # Same directory configurations as above
# </VirtualHost>
```

---

## 🔒 Security Setup

### SELinux Configuration

```bash
# Check SELinux status
getenforce  # Should show "Enforcing"

# Allow Apache to connect to database
setsebool -P httpd_can_network_connect_db on

# Allow Apache to send email (for notifications)
setsebool -P httpd_can_sendmail on

# Allow Apache to connect to network (for API calls)
setsebool -P httpd_can_network_connect on

# Set correct SELinux context for uploads directory
chcon -R -t httpd_sys_rw_content_t /var/www/html/uploads
chcon -R -t httpd_sys_rw_content_t /var/www/html/uploads/videos
chcon -R -t httpd_sys_rw_content_t /var/www/html/uploads/materials
chcon -R -t httpd_sys_rw_content_t /var/www/html/uploads/posters
chcon -R -t httpd_sys_rw_content_t /var/www/html/uploads/thumbnails
chcon -R -t httpd_sys_rw_content_t /var/www/html/uploads/profile_pictures

# Restore default SELinux contexts
restorecon -R /var/www/html

# If you encounter SELinux denials, check logs:
ausearch -m AVC -ts recent
# Or
tail -f /var/log/audit/audit.log | grep denied
```

### File Permissions

```bash
# Set ownership
chown -R apache:apache /var/www/html

# Set directory permissions (755)
find /var/www/html -type d -exec chmod 755 {} \;

# Set file permissions (644)
find /var/www/html -type f -exec chmod 644 {} \;

# Make uploads directory writable
chmod 775 /var/www/html/uploads
chmod 775 /var/www/html/uploads/videos
chmod 775 /var/www/html/uploads/materials
chmod 775 /var/www/html/uploads/posters
chmod 775 /var/www/html/uploads/thumbnails
chmod 775 /var/www/html/uploads/profile_pictures

# Protect sensitive files
chmod 600 /var/www/html/includes/db_connect.php
chmod 600 /var/www/html/composer.json
chmod 600 /var/www/html/composer.lock

# Protect configuration files
chmod 644 /etc/httpd/conf/httpd.conf
chmod 644 /etc/php.ini
chmod 600 /etc/my.cnf.d/server.cnf
```

### SSL Certificate Setup (Let's Encrypt)

```bash
# Install Certbot (if not already installed)
dnf install certbot python3-certbot-apache -y

# Obtain SSL certificate
certbot --apache -d your-domain.com -d www.your-domain.com

# Follow the prompts:
# 1. Enter email address
# 2. Agree to terms
# 3. Choose whether to share email with EFF
# 4. Choose whether to redirect HTTP to HTTPS (recommended: 2)

# Test certificate renewal
certbot renew --dry-run

# Set up automatic renewal (cron)
echo "0 0,12 * * * root /usr/bin/certbot renew --quiet" >> /etc/crontab

# Verify SSL configuration
openssl s_client -connect your-domain.com:443 -servername your-domain.com
```

### Fail2ban Setup (Brute Force Protection)

```bash
# Install fail2ban
dnf install fail2ban fail2ban-firewalld -y

# Create jail configuration
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
destemail = admin@your-domain.com
sendername = Fail2Ban
action = %(action_mwl)s

[sshd]
enabled = true
port = ssh
logpath = /var/log/secure

[httpd-auth]
enabled = true
port = http,https
logpath = /var/log/httpd/error_log

[httpd-badbots]
enabled = true
port = http,https
logpath = /var/log/httpd/access_log
maxretry = 2

[php-url-fopen]
enabled = true
port = http,https
logpath = /var/log/httpd/access_log
EOF

# Enable and start fail2ban
systemctl enable fail2ban
systemctl start fail2ban

# Check status
fail2ban-client status
```

---

## 📊 Performance Tuning

### System Limits (/etc/security/limits.conf)

```bash
# Add to /etc/security/limits.conf
apache soft nofile 65535
apache hard nofile 65535
mysql soft nofile 65535
mysql hard nofile 65535

# Add to /etc/sysctl.conf
cat >> /etc/sysctl.conf << 'EOF'
# Network Performance
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216

# Connection Handling
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 4096
net.ipv4.tcp_fin_timeout = 15
net.ipv4.tcp_tw_reuse = 1

# File Handles
fs.file-max = 2097152
EOF

# Apply changes
sysctl -p
```

### Swap Configuration

```bash
# Check current swap
free -h
swapon --show

# For 24GB RAM, 16GB swap is recommended
# For 16GB RAM, 16GB swap is essential

# If swap doesn't exist, create swap file
fallocate -l 16G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile

# Make permanent
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Adjust swappiness (lower = less aggressive swap usage)
echo 'vm.swappiness=10' >> /etc/sysctl.conf
sysctl -p
```

### Logrotate Configuration

```bash
# Create logrotate config for application
cat > /etc/logrotate.d/learning-platform << 'EOF'
/var/log/httpd/learning_platform*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 apache apache
    sharedscripts
    postrotate
        /bin/systemctl reload httpd > /dev/null 2>&1 || true
    endscript
}

/var/log/php_errors.log {
    daily
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 apache apache
}

/var/log/mariadb/*.log {
    daily
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 mysql mysql
    sharedscripts
    postrotate
        /bin/systemctl reload mariadb > /dev/null 2>&1 || true
    endscript
}
EOF
```

---

## 💾 Backup Strategy

### Automated Backup Script

```bash
# Create backup script
cat > /root/backup-learning-platform.sh << 'EOF'
#!/bin/bash
# Learning Platform Backup Script
# Author: System Administrator
# Date: October 31, 2025

# Configuration
BACKUP_DIR="/backup"
DATE=$(date +%Y%m%d_%H%M%S)
MYSQL_USER="root"
MYSQL_PASS="your_mysql_root_password"
DB_NAME="learning_platform"
RETENTION_DAYS=7

# Create backup directories
mkdir -p $BACKUP_DIR/mysql/daily
mkdir -p $BACKUP_DIR/files/weekly

# MySQL Backup
echo "Starting MySQL backup..."
mysqldump -u $MYSQL_USER -p$MYSQL_PASS $DB_NAME \
  --single-transaction \
  --quick \
  --lock-tables=false \
  > $BACKUP_DIR/mysql/daily/db_$DATE.sql

# Compress database backup
gzip $BACKUP_DIR/mysql/daily/db_$DATE.sql

# File Backup (weekly on Sundays)
if [ $(date +%u) -eq 7 ]; then
  echo "Starting weekly file backup..."
  tar -czf $BACKUP_DIR/files/weekly/files_$DATE.tar.gz \
    /var/www/html/uploads \
    /var/www/html/includes \
    /etc/httpd/conf \
    /etc/php.ini \
    /etc/php-fpm.d \
    /etc/my.cnf.d
fi

# Remove old backups
find $BACKUP_DIR/mysql/daily -name "db_*.sql.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR/files/weekly -name "files_*.tar.gz" -mtime +28 -delete

# Log completion
echo "Backup completed: $DATE" >> /var/log/backup.log

# Verify backup
if [ -f "$BACKUP_DIR/mysql/daily/db_$DATE.sql.gz" ]; then
  echo "Database backup successful"
else
  echo "ERROR: Database backup failed!" | mail -s "Backup Error" admin@your-domain.com
fi
EOF

# Make executable
chmod +x /root/backup-learning-platform.sh

# Test backup
/root/backup-learning-platform.sh

# Add to crontab (daily at 2 AM)
crontab -e
# Add this line:
# 0 2 * * * /root/backup-learning-platform.sh > /dev/null 2>&1
```

### Database Restore Procedure

```bash
# Restore from backup
# 1. List available backups
ls -lh /backup/mysql/daily/

# 2. Decompress backup
gunzip /backup/mysql/daily/db_20251031_020000.sql.gz

# 3. Restore database
mysql -u root -p learning_platform < /backup/mysql/daily/db_20251031_020000.sql

# 4. Verify restoration
mysql -u root -p -e "USE learning_platform; SHOW TABLES;"
```

---

## 📈 Monitoring

### System Monitoring Tools

```bash
# Install monitoring tools
dnf install sysstat iotop nethogs -y

# Enable and start sysstat
systemctl enable sysstat
systemctl start sysstat

# View system statistics
# CPU usage:
mpstat 1

# Memory usage:
free -h
vmstat 1

# Disk I/O:
iostat -x 1

# Network usage:
nethogs

# Disk usage:
df -h
ncdu /

# Process monitoring:
htop

# Apache status:
systemctl status httpd

# PHP-FPM status:
systemctl status php-fpm

# MySQL status:
systemctl status mariadb
```

### Apache Monitoring

```bash
# Enable server-status module
cat >> /etc/httpd/conf.d/server-status.conf << 'EOF'
<Location /server-status>
    SetHandler server-status
    Require ip 127.0.0.1
    Require ip 192.168.1.0/24
</Location>

ExtendedStatus On
EOF

# Restart Apache
systemctl restart httpd

# View status
curl http://localhost/server-status
```

### MySQL Monitoring

```bash
# Login to MySQL
mysql -u root -p

# Check status
SHOW STATUS;
SHOW PROCESSLIST;
SHOW VARIABLES LIKE 'max_connections';
SHOW STATUS LIKE 'Threads_connected';

# Check slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;

# Check table sizes
SELECT 
  table_schema AS 'Database',
  table_name AS 'Table',
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES 
WHERE table_schema = 'learning_platform'
ORDER BY (data_length + index_length) DESC;
```

### Log Monitoring

```bash
# Apache logs
tail -f /var/log/httpd/error_log
tail -f /var/log/httpd/access_log
tail -f /var/log/httpd/learning_platform_error.log

# PHP logs
tail -f /var/log/php_errors.log
tail -f /var/log/php-fpm/www-error.log

# MySQL logs
tail -f /var/log/mariadb/mariadb.log
tail -f /var/log/mariadb/slow-query.log

# System logs
journalctl -u httpd -f
journalctl -u php-fpm -f
journalctl -u mariadb -f

# SELinux denials
ausearch -m AVC -ts recent
```

---

## 🎯 Deployment Checklist

### Pre-Deployment

```
□ Hardware installed and tested
□ RHEL 8 installed with partitions configured
□ Network configured (static IP, DNS)
□ All software packages installed
□ Services enabled and running
□ Firewall configured
□ SELinux configured
□ Database created and secured
□ Backups configured and tested
```

### Application Deployment

```
□ Upload application files to /var/www/html
□ Run composer install
□ Import database schema
□ Configure database connection (includes/db_connect.php)
□ Set file permissions
□ Test application in browser
□ Configure SSL certificate
□ Test HTTPS access
□ Set up cron jobs (if needed)
□ Configure email settings (if needed)
```

### Post-Deployment

```
□ Test user registration
□ Test login functionality
□ Upload test video module
□ Test video playback
□ Test quiz functionality
□ Test progress tracking
□ Test admin panel
□ Verify backups working
□ Set up monitoring alerts
□ Document admin credentials (securely)
□ Train administrators
```

---

## 🆘 Troubleshooting

### Common Issues and Solutions

#### Issue 1: Cannot connect to database

```bash
# Check MariaDB is running
systemctl status mariadb

# Check MySQL socket
ls -l /var/lib/mysql/mysql.sock

# Test connection
mysql -u root -p

# Check SELinux
getsebool httpd_can_network_connect_db

# Solution: Enable SELinux boolean
setsebool -P httpd_can_network_connect_db on
```

#### Issue 2: 403 Forbidden Error

```bash
# Check file permissions
ls -lZ /var/www/html

# Check SELinux context
ls -Z /var/www/html

# Solution: Fix SELinux context
restorecon -R /var/www/html
chown -R apache:apache /var/www/html
```

#### Issue 3: PHP uploads not working

```bash
# Check PHP configuration
php -i | grep upload_max_filesize
php -i | grep post_max_size

# Check directory permissions
ls -ld /var/www/html/uploads

# Check SELinux
getsebool httpd_sys_rw_content_t

# Solution:
chcon -R -t httpd_sys_rw_content_t /var/www/html/uploads
chmod 775 /var/www/html/uploads
```

#### Issue 4: Slow performance

```bash
# Check system resources
htop
free -h
df -h

# Check MySQL
mysql -u root -p -e "SHOW PROCESSLIST;"

# Check Apache
curl http://localhost/server-status

# Solution: Tune configurations based on findings
```

---

## 📞 Support & Contacts

```
Technical Support:
├─ Red Hat Customer Portal: https://access.redhat.com
├─ RHEL Documentation: https://access.redhat.com/documentation/en-us/red_hat_enterprise_linux/8
└─ Community Forums: https://www.redhat.com/en/services/support

Learning Platform Support:
├─ Application Documentation: /docs/README.md
├─ Deployment Guide: /docs/RHEL_DEPLOYMENT_GUIDE.md
└─ Video Format Guide: /docs/VIDEO_FORMAT_GUIDE.md

Emergency Contacts:
├─ System Administrator: [Your contact]
├─ Database Administrator: [Your contact]
└─ Network Administrator: [Your contact]
```

---

## 📝 Revision History

```
Version 1.0 - October 31, 2025
├─ Initial documentation
├─ RHEL 8 specific instructions
├─ 6 modules, 500 users, 100+ concurrent
└─ Hardware: 6 cores / 24GB / 256GB
```

---

**END OF DOCUMENT**

For additional assistance, refer to:
- `/docs/RHEL_DEPLOYMENT_GUIDE.md` - Complete deployment guide
- `/docs/DEPLOYMENT_REQUIREMENTS.md` - Detailed requirements
- `/docs/MODULE_NUMBERING_GUIDE.md` - Content organization
- `/docs/VIDEO_FORMAT_GUIDE.md` - Video preparation
