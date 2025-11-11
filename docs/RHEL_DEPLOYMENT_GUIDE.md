# Red Hat Enterprise Linux Deployment Guide
## Information Security Learning Platform v6.0
### Complete Step-by-Step Installation for Bank Environment

**Target Environment:** Red Hat Enterprise Linux (RHEL) 8/9  
**Date:** October 28, 2025  
**Version:** 6.0  
**Deployment Type:** Internal Bank Server (500 Users)

---

## 📋 Table of Contents

1. [Hardware & Network Requirements](#hardware--network-requirements)
2. [Pre-Deployment Requirements](#pre-deployment-requirements)
3. [System Preparation](#system-preparation)
4. [Web Server Installation](#web-server-installation)
5. [Database Installation](#database-installation)
6. [Application Deployment](#application-deployment)
7. [Security Configuration](#security-configuration)
8. [Network Configuration](#network-configuration)
9. [Testing & Verification](#testing-verification)
10. [Post-Deployment](#post-deployment)
11. [Troubleshooting](#troubleshooting)

---

## 💻 Hardware & Network Requirements

### Recommended Hardware Specifications (500 Users)

```
Server Configuration:
┌─────────────────────────────────────────┐
│ CPU:      8 cores (Intel Xeon)         │
│ RAM:      16GB minimum, 32GB ideal     │
│ Storage:  500GB minimum                │
│   ├─ OS:        120GB (SSD)            │
│   ├─ Database:  100GB (SSD)            │
│   └─ Videos:    280GB+ (SSD/HDD)       │
│ Network:  1Gbps Ethernet               │
│ Backup:   External storage/NAS         │
└─────────────────────────────────────────┘
```

### Minimum Hardware Requirements

```
For Testing/Small Deployment (<100 users):
- CPU: 4 cores
- RAM: 8GB
- Storage: 250GB SSD
- Network: 100Mbps
```

---

## 🌐 Network Requirements

### Required Network Access

#### 1. **Inbound Connections** (External → Server)

| Port | Protocol | Service | From | Required |
|------|----------|---------|------|----------|
| **22** | TCP | SSH (Admin) | Admin workstations | ✅ Yes |
| **80** | TCP | HTTP (Auto-redirect) | All users | ✅ Yes |
| **443** | TCP | HTTPS (Main access) | All users | ✅ Yes |

**Important:** Port 3306 (MySQL) should **NOT** be exposed externally.

#### 2. **Outbound Connections** (Server → External)

| Port | Protocol | Purpose | Destination | Required |
|------|----------|---------|-------------|----------|
| **80** | TCP | Package updates | RHEL repos | ✅ Yes |
| **443** | TCP | Secure updates | RHEL repos | ✅ Yes |
| **53** | UDP | DNS resolution | DNS servers | ✅ Yes |
| **123** | UDP | Time sync (NTP) | Time servers | ⚠️ Recommended |
| **25/587** | TCP | Email (optional) | SMTP server | ❌ Optional |

#### 3. **Internal Network Requirements**

```
Server Configuration:
├─ Static IP Address: Required
├─ Subnet Mask: /24 or as per bank network
├─ Default Gateway: Bank's network gateway
├─ DNS Servers: Bank's internal DNS (primary + secondary)
└─ Hostname: learning.bank.local (or bank-specified)

Example Configuration:
IP Address:   192.168.10.100
Subnet:       255.255.255.0 (/24)
Gateway:      192.168.10.1
DNS Primary:  192.168.10.2
DNS Secondary: 192.168.10.3
```

---

### Bandwidth Requirements

#### Expected Traffic Load (500 Staff)

| Scenario | Concurrent Users | Bandwidth Needed | Recommendation |
|----------|------------------|------------------|----------------|
| **Normal Hours** | 50-100 | 50-100 Mbps | 200 Mbps |
| **Peak Training** | 150-200 | 150-200 Mbps | 500 Mbps |
| **Maximum Load** | 300-400 | 300-400 Mbps | **1 Gbps** ⭐ |
| **All Staff** | 500 (rare) | 500 Mbps | 1 Gbps |

**Calculation Basis:**
- Video streaming: ~1 Mbps per user (1080p)
- Page loading: ~100 KB per page
- PDF downloads: Burst traffic

**Recommended Network Interface:**
- **1Gbps Ethernet** (standard)
- Consider **dual NICs** (network teaming) for redundancy

---

### Firewall Configuration

#### Bank Firewall Rules (Request from Network Team)

**Allow Inbound Traffic:**
```
Source: Bank internal network (e.g., 192.168.0.0/16)
Destination: Learning Server (e.g., 192.168.10.100)
Ports: 80 (HTTP), 443 (HTTPS)
Protocol: TCP
Action: ALLOW
```

**Allow Outbound for Updates:**
```
Source: Learning Server (192.168.10.100)
Destination: Internet (RHEL repos)
Ports: 80, 443
Protocol: TCP
Action: ALLOW
```

**SSH Access (Admin Only):**
```
Source: IT Admin network (e.g., 192.168.1.0/24)
Destination: Learning Server (192.168.10.100)
Port: 22
Protocol: TCP
Action: ALLOW
```

---

### DNS Configuration

#### Required DNS Records (Request from DNS Admin)

**A Record (IPv4):**
```
Name: learning
Type: A
Value: 192.168.10.100
TTL: 3600

Result: learning.bank.local → 192.168.10.100
```

**Optional CNAME:**
```
Name: elearning
Type: CNAME
Value: learning.bank.local

Result: elearning.bank.local → learning.bank.local → 192.168.10.100
```

**Reverse DNS (PTR):**
```
100.10.168.192.in-addr.arpa → learning.bank.local
```

---

### SSL/TLS Certificate Requirements

#### Option 1: Internal CA Certificate (Recommended for Banks)

**Request certificate from bank's Certificate Authority:**
```
Certificate Request Details:
- Common Name (CN): learning.bank.local
- Organization (O): [Bank Name]
- Country (C): MY
- Key Size: 2048-bit RSA or 256-bit ECC
- Validity: 1-2 years
- SAN (Subject Alternative Names):
  - learning.bank.local
  - elearning.bank.local
  - 192.168.10.100
```

**Files needed:**
- Certificate file: `learning.bank.local.crt`
- Private key: `learning.bank.local.key`
- CA bundle: `ca-bundle.crt`

#### Option 2: Self-Signed Certificate (Testing Only)

**Generate on server (temporary):**
```bash
# Will be shown in installation steps
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/pki/tls/private/learning.key \
  -out /etc/pki/tls/certs/learning.crt
```

⚠️ **Warning:** Self-signed certificates show browser warnings. Use only for testing.

---

### Network Topology

```
Bank Network Topology:

                    ┌─────────────┐
                    │   Internet  │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │ Bank Edge   │
                    │  Firewall   │
                    └──────┬──────┘
                           │
         ┌─────────────────┼─────────────────┐
         │                                   │
    ┌────▼─────┐                      ┌─────▼────┐
    │  DMZ     │                      │ Internal │
    │  Zone    │                      │ Network  │
    └──────────┘                      └─────┬────┘
                                            │
                                      ┌─────▼────────┐
                                      │  Learning    │
                                      │   Server     │
                                      │ 192.168.10.  │
                                      │     100      │
                                      └──────────────┘
                                            │
                                      ┌─────▼────────┐
                                      │   Storage    │
                                      │    (NAS)     │
                                      └──────────────┘

User Access:
- Staff workstations → Internal Network → Learning Server
- External access: Not recommended (use VPN if needed)
```

---

### Network Security Recommendations

#### 1. **Network Segmentation**
- Place server in dedicated application server subnet
- Separate from user workstation network
- Isolate from DMZ and guest networks

#### 2. **Access Control**
- Use **VLAN** for server isolation
- Implement **MAC address filtering** (optional)
- Enable **802.1X authentication** (if supported)

#### 3. **Traffic Monitoring**
- Enable **NetFlow** or **sFlow** for traffic analysis
- Set up **IDS/IPS** alerts for unusual traffic
- Monitor bandwidth usage during peak hours

#### 4. **Redundancy (Optional for High Availability)**
```
Network Redundancy Options:
├─ Dual NICs with LACP bonding (link aggregation)
├─ Redundant switches (failover)
├─ Multiple upstream links
└─ Backup network path
```

---

### Network Performance Tuning

#### Optimize Network Stack (Will configure during deployment)

```bash
# These settings will be added to /etc/sysctl.conf
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216
net.ipv4.tcp_congestion_control = bbr
net.core.netdev_max_backlog = 5000
```

**Purpose:** Handle high concurrent connections efficiently

---

### Network Monitoring Tools

**Monitor network performance:**

```bash
# Bandwidth usage
iftop -i eth0

# Connection tracking
netstat -an | grep :443 | wc -l

# Network statistics
ss -s

# Packet capture (troubleshooting)
tcpdump -i eth0 port 443
```

---

### Expected Network Load (500 Users)

#### Daily Traffic Estimation

```
Typical Day Traffic:
┌────────────────────────────────────────┐
│ Time     │ Users │ Traffic  │ Mbps    │
├──────────┼───────┼──────────┼─────────┤
│ 08-09 AM │  50   │ 2 GB     │  50     │
│ 09-12 PM │ 100   │ 10 GB    │  80     │
│ 12-02 PM │  30   │ 1 GB     │  20     │
│ 02-05 PM │ 150   │ 20 GB    │  150    │
│ 05-06 PM │  40   │ 2 GB     │  50     │
├──────────┼───────┼──────────┼─────────┤
│ Daily    │  500  │ ~35 GB   │ Peak:   │
│ Total    │ total │          │ 150 Mbps│
└────────────────────────────────────────┘

Monthly Traffic: ~750 GB - 1 TB
```

**Storage Growth:**
- User uploads (certificates): ~100 MB/month
- Logs: ~500 MB/month
- Database growth: ~1 GB/month
- Video storage (static): 50-100 GB

---

### Network Troubleshooting Checklist

**Before deployment, verify:**

- [ ] Server has static IP configured
- [ ] DNS resolution works (ping google.com)
- [ ] Gateway is reachable (ping default gateway)
- [ ] Internal DNS resolves (nslookup learning.bank.local)
- [ ] Firewall allows ports 80, 443 from user network
- [ ] SSH access works from admin workstation
- [ ] NTP time sync is working (timedatectl)
- [ ] Network speed test: iperf3 (if available)

**Commands to verify:**
```bash
# Check IP configuration
ip addr show

# Check routing
ip route show

# Test DNS
nslookup google.com
nslookup learning.bank.local

# Test connectivity
ping -c 4 8.8.8.8
ping -c 4 bank.local

# Check firewall
firewall-cmd --list-all

# Monitor network
ifconfig eth0
```

---

## 🔐 Pre-Deployment Requirements

### Access Requirements
- [ ] Root or sudo access to RHEL server
- [ ] SSH access credentials
- [ ] IP address or hostname of the server
- [ ] Network access from workstation to server

### Information Needed
- [ ] **Server IP Address:** _________________
- [ ] **Server Hostname:** _________________
- [ ] **Database Name:** learning_platform_v6
- [ ] **Database User:** learning_platform_user
- [ ] **Database Password:** (generate secure password)
- [ ] **Admin Username:** admin.gov
- [ ] **Admin Email:** _________________

### Files to Prepare
- [ ] Application source code (this repository)
- [ ] SSL certificate files (if using HTTPS)
- [ ] Video content files
- [ ] PDF materials

---

## 📦 STEP 1: System Preparation

### 1.1 Connect to Server

```bash
# From your workstation (Windows PowerShell or Linux terminal)
ssh username@server_ip_address

# Example:
# ssh admin@192.168.1.100
# or
# ssh admin@learning.yourbank.com
```

**Enter your password when prompted.**

---

### 1.2 Update System

```bash
# Switch to root user
sudo su -

# Update all packages
dnf update -y

# Reboot if kernel was updated (optional but recommended)
# reboot

# After reboot, reconnect
# ssh username@server_ip_address
# sudo su -
```

**⏱ Expected Time:** 5-15 minutes depending on updates

---

### 1.3 Install Required Repositories

```bash
# Enable EPEL repository (Extra Packages for Enterprise Linux)
dnf install -y epel-release

# Enable PowerTools/CodeReady Builder (for some dependencies)
# For RHEL 8:
dnf config-manager --set-enabled powertools

# For RHEL 9:
dnf config-manager --set-enabled crb

# Update repository cache
dnf clean all
dnf makecache
```

---

### 1.4 Install Essential Tools

```bash
# Install basic utilities
dnf install -y \
    wget \
    curl \
    vim \
    nano \
    unzip \
    tar \
    git \
    policycoreutils-python-utils \
    firewalld

# Start and enable firewall
systemctl start firewalld
systemctl enable firewalld
```

---

## 🌐 STEP 2: Web Server Installation (Apache)

### 2.1 Install Apache Web Server

```bash
# Install Apache (httpd)
dnf install -y httpd httpd-tools mod_ssl

# Start and enable Apache
systemctl start httpd
systemctl enable httpd

# Check Apache status
systemctl status httpd
```

**✅ Expected Output:** "active (running)" in green

---

### 2.2 Configure Firewall for Web Traffic

```bash
# Allow HTTP traffic (port 80)
firewall-cmd --permanent --add-service=http

# Allow HTTPS traffic (port 443)
firewall-cmd --permanent --add-service=https

# Reload firewall to apply changes
firewall-cmd --reload

# Verify firewall rules
firewall-cmd --list-all
```

**✅ Expected Output:** Should show "http" and "https" in services

---

### 2.3 Test Apache Installation

```bash
# Check if Apache is listening on port 80
ss -tlnp | grep :80

# Get server IP address
ip addr show | grep "inet " | grep -v 127.0.0.1
```

**🌐 Test in Browser:**
- Open browser on your workstation
- Navigate to: `http://server_ip_address`
- You should see the RHEL Apache test page

---

## 🐘 STEP 3: PHP Installation

### 3.1 Install PHP 8.0 and Extensions

```bash
# Install PHP and required extensions
dnf install -y \
    php \
    php-mysqlnd \
    php-gd \
    php-xml \
    php-mbstring \
    php-json \
    php-zip \
    php-curl \
    php-opcache \
    php-intl \
    php-bcmath \
    php-soap \
    php-pdo \
    php-cli

# Verify PHP installation
php -v
```

**✅ Expected Output:** PHP 8.0.x or PHP 8.1.x

---

### 3.2 Configure PHP Settings

```bash
# Backup original PHP configuration
cp /etc/php.ini /etc/php.ini.backup

# Edit PHP configuration
nano /etc/php.ini
```

**Find and modify these lines (use Ctrl+W to search in nano):**

```ini
# Search for each setting and change the values:

max_execution_time = 300
max_input_time = 300
memory_limit = 256M
post_max_size = 500M
upload_max_filesize = 500M
date.timezone = Asia/Phnom_Penh

# For session settings, find and set:
session.gc_maxlifetime = 3600
session.save_path = "/var/lib/php/session"

# Enable opcache (should already be enabled)
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
```

**Save and exit:**
- Press `Ctrl+X`
- Press `Y` to confirm
- Press `Enter` to save

---

### 3.3 Create PHP Session Directory

```bash
# Create session directory if not exists
mkdir -p /var/lib/php/session

# Set proper permissions
chown -R apache:apache /var/lib/php/session
chmod 700 /var/lib/php/session

# Configure SELinux context
semanage fcontext -a -t httpd_sys_rw_content_t "/var/lib/php/session(/.*)?"
restorecon -Rv /var/lib/php/session
```

---

### 3.4 Restart Apache to Load PHP

```bash
# Restart Apache
systemctl restart httpd

# Check Apache status
systemctl status httpd
```

---

### 3.5 Test PHP Installation

```bash
# Create PHP info page
echo '<?php phpinfo(); ?>' > /var/www/html/info.php

# Set permissions
chmod 644 /var/www/html/info.php
```

**🌐 Test in Browser:**
- Navigate to: `http://server_ip_address/info.php`
- You should see PHP information page

**⚠️ SECURITY: Delete this file after testing**
```bash
rm -f /var/www/html/info.php
```

---

## 🗄️ STEP 4: Database Installation (MySQL/MariaDB)

### 4.1 Install MariaDB Server

```bash
# Install MariaDB
dnf install -y mariadb-server mariadb

# Start and enable MariaDB
systemctl start mariadb
systemctl enable mariadb

# Check MariaDB status
systemctl status mariadb
```

**✅ Expected Output:** "active (running)" in green

---

### 4.2 Secure MariaDB Installation

```bash
# Run security script
mysql_secure_installation
```

**Answer the prompts as follows:**

```
Enter current password for root (enter for none): [Press Enter]

Set root password? [Y/n] Y
New password: [Enter a strong password]
Re-enter new password: [Re-enter the password]

Remove anonymous users? [Y/n] Y
Disallow root login remotely? [Y/n] Y
Remove test database and access to it? [Y/n] Y
Reload privilege tables now? [Y/n] Y
```

**📝 IMPORTANT:** Write down the MySQL root password securely!

---

### 4.3 Create Database and User

```bash
# Login to MySQL
mysql -u root -p
```

**Enter the root password you just set.**

**Execute these SQL commands:**

```sql
-- Create database
CREATE DATABASE learning_platform_v6 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (replace 'YourSecurePassword' with a strong password)
CREATE USER 'learning_platform_user'@'localhost' IDENTIFIED BY 'YourSecurePassword';

-- Grant privileges
GRANT ALL PRIVILEGES ON learning_platform_v6.* TO 'learning_platform_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify database was created
SHOW DATABASES;

-- Exit MySQL
EXIT;
```

**📝 IMPORTANT:** Write down the database username and password!

---

### 4.4 Configure MariaDB for Performance

```bash
# Backup MariaDB configuration
cp /etc/my.cnf.d/server.cnf /etc/my.cnf.d/server.cnf.backup

# Edit MariaDB configuration
nano /etc/my.cnf.d/server.cnf
```

**Add these lines under [mysqld] section:**

```ini
[mysqld]
# Performance settings for 16GB RAM server
innodb_buffer_pool_size = 4G
innodb_log_file_size = 256M
max_connections = 500
query_cache_size = 128M
query_cache_type = 1
tmp_table_size = 64M
max_heap_table_size = 64M
```

**Save and exit** (Ctrl+X, Y, Enter)

```bash
# Restart MariaDB
systemctl restart mariadb

# Check status
systemctl status mariadb
```

---

## 📂 STEP 5: Application Deployment

### 5.1 Prepare Application Directory

```bash
# Navigate to web root
cd /var/www/html

# Remove default Apache page
rm -f index.html

# Create application directory
mkdir -p learning_platform
cd learning_platform
```

---

### 5.2 Upload Application Files

**Option A: Using SCP from Windows (PowerShell)**

```powershell
# From your Windows machine where the code is located
# Open PowerShell in the project directory

scp -r * username@server_ip:/var/www/html/learning_platform/

# Example:
# scp -r * admin@192.168.1.100:/var/www/html/learning_platform/
```

**Option B: Using SFTP Client (WinSCP or FileZilla)**

1. Open WinSCP or FileZilla
2. Connect to server using SFTP
3. Navigate to `/var/www/html/learning_platform/`
4. Upload all files from your local project folder

**Option C: Using Git (if code is in repository)**

```bash
# On the server
cd /var/www/html/learning_platform

# Clone repository
git clone https://github.com/reachhwasup/learning_platform_v6_final.git .

# Note the dot (.) at the end to clone into current directory
```

---

### 5.3 Set File Permissions

```bash
# Navigate to application directory
cd /var/www/html/learning_platform

# Set ownership to Apache user
chown -R apache:apache /var/www/html/learning_platform

# Set directory permissions
find /var/www/html/learning_platform -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/html/learning_platform -type f -exec chmod 644 {} \;

# Set writable permissions for upload directories
chmod -R 775 /var/www/html/learning_platform/uploads
chown -R apache:apache /var/www/html/learning_platform/uploads
```

---

### 5.4 Configure SELinux Contexts

```bash
# Set SELinux context for web content
semanage fcontext -a -t httpd_sys_content_t "/var/www/html/learning_platform(/.*)?"
restorecon -Rv /var/www/html/learning_platform

# Allow Apache to write to uploads directory
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/learning_platform/uploads(/.*)?"
restorecon -Rv /var/www/html/learning_platform/uploads

# Allow Apache to connect to database
setsebool -P httpd_can_network_connect_db 1

# Allow Apache to send emails (if needed)
setsebool -P httpd_can_sendmail 1
```

---

### 5.5 Configure Database Connection

```bash
# Edit database configuration file
nano /var/www/html/learning_platform/includes/db_connect.php
```

**Update these values:**

```php
// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'learning_platform_v6');
define('DB_USER', 'learning_platform_user');
define('DB_PASS', 'YourSecurePassword');  // Use the password you created earlier
define('DB_CHARSET', 'utf8mb4');
```

**Save and exit** (Ctrl+X, Y, Enter)

---

### 5.6 Install Composer Dependencies

```bash
# Install Composer (PHP package manager)
cd /tmp
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Navigate to application directory
cd /var/www/html/learning_platform

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set permissions on vendor directory
chown -R apache:apache vendor/
```

---

### 5.7 Import Database Schema

**Transfer the database SQL file to server:**

```bash
# Create a SQL dump file if you have an existing database
# Or create the schema manually

# Login to MySQL
mysql -u learning_platform_user -p learning_platform_v6
```

**Create the database tables (paste this SQL):**

```sql
-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    staff_id VARCHAR(20) UNIQUE,
    department_id INT,
    email VARCHAR(100),
    phone_number VARCHAR(20),
    gender ENUM('Male', 'Female', 'Other'),
    position VARCHAR(100),
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive', 'locked') DEFAULT 'active',
    profile_picture VARCHAR(255) DEFAULT 'default_avatar.jpg',
    force_password_change TINYINT(1) DEFAULT 0,
    failed_login_attempts INT DEFAULT 0,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_staff_id (staff_id),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modules table
CREATE TABLE modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    module_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (module_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Videos table
CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT,
    video_path VARCHAR(255),
    thumbnail_path VARCHAR(255),
    duration INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materials table
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT,
    material_path VARCHAR(255),
    material_name VARCHAR(255),
    file_size BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions table
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT,
    question_text TEXT NOT NULL,
    question_type ENUM('single', 'multiple') DEFAULT 'single',
    is_final_exam_question TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    INDEX idx_module (module_id),
    INDEX idx_exam (is_final_exam_question)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question Options table
CREATE TABLE question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Progress table
CREATE TABLE user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    module_id INT,
    video_completed TINYINT(1) DEFAULT 0,
    quiz_completed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_module (user_id, module_id),
    INDEX idx_user (user_id),
    INDEX idx_module (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Answers table
CREATE TABLE user_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    assessment_id INT NULL,
    question_id INT,
    selected_option_id INT,
    is_correct TINYINT(1) DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES question_options(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_question (question_id),
    INDEX idx_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Final Assessments table
CREATE TABLE final_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    score INT DEFAULT 0,
    status ENUM('passed', 'failed') DEFAULT 'failed',
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Posters table
CREATE TABLE posters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    category VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password History table
CREATE TABLE password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default department
INSERT INTO departments (name, description) VALUES 
('IT Department', 'Information Technology'),
('HR Department', 'Human Resources'),
('Finance Department', 'Finance and Accounting'),
('Security Department', 'Information Security');

-- Insert default admin user
-- Password: admin123 (CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN!)
INSERT INTO users (username, password, first_name, last_name, staff_id, department_id, email, role, status)
VALUES ('admin.gov', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
        'System', 'Administrator', 'ADMIN001', 1, 'admin@yourbank.com', 'admin', 'active');

-- Exit
EXIT;
```

**⚠️ SECURITY NOTE:** The default admin password is `admin123`. CHANGE IT IMMEDIATELY after first login!

---

## ⚙️ STEP 6: Apache Virtual Host Configuration

### 6.1 Create Virtual Host Configuration

```bash
# Create virtual host configuration file
nano /etc/httpd/conf.d/learning_platform.conf
```

**Add this configuration:**

```apache
<VirtualHost *:80>
    ServerName learning.yourbank.com
    ServerAlias www.learning.yourbank.com
    DocumentRoot /var/www/html/learning_platform
    
    <Directory /var/www/html/learning_platform>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Deny access to sensitive files
        <FilesMatch "^\.">
            Require all denied
        </FilesMatch>
        
        <FilesMatch "(composer\.json|composer\.lock|\.git)">
            Require all denied
        </FilesMatch>
    </Directory>
    
    # Uploads directory
    <Directory /var/www/html/learning_platform/uploads>
        Options -Indexes
        AllowOverride None
        Require all granted
        
        # Prevent PHP execution in uploads
        php_flag engine off
        AddType text/plain .php .php3 .php4 .phtml .pl .py
    </Directory>
    
    # Logging
    ErrorLog /var/log/httpd/learning_platform_error.log
    CustomLog /var/log/httpd/learning_platform_access.log combined
    
    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</VirtualHost>

# HTTPS configuration (if SSL certificate is available)
# <VirtualHost *:443>
#     ServerName learning.yourbank.com
#     ServerAlias www.learning.yourbank.com
#     DocumentRoot /var/www/html/learning_platform
#     
#     SSLEngine on
#     SSLCertificateFile /etc/pki/tls/certs/learning.crt
#     SSLCertificateKeyFile /etc/pki/tls/private/learning.key
#     SSLCertificateChainFile /etc/pki/tls/certs/learning-chain.crt
#     
#     # Same directory configurations as above
#     <Directory /var/www/html/learning_platform>
#         Options -Indexes +FollowSymLinks
#         AllowOverride All
#         Require all granted
#     </Directory>
#     
#     ErrorLog /var/log/httpd/learning_platform_ssl_error.log
#     CustomLog /var/log/httpd/learning_platform_ssl_access.log combined
# </VirtualHost>
```

**Save and exit** (Ctrl+X, Y, Enter)

---

### 6.2 Test and Restart Apache

```bash
# Test Apache configuration
httpd -t

# If output says "Syntax OK", restart Apache
systemctl restart httpd

# Check Apache status
systemctl status httpd

# Check for errors in logs
tail -f /var/log/httpd/error_log
# Press Ctrl+C to exit
```

---

## 🔒 STEP 7: Security Configuration

### 7.1 Configure SELinux (Already partially done)

```bash
# Verify SELinux is in enforcing mode
getenforce

# If it shows "Permissive" or "Disabled", enable it:
# setenforce 1

# Make SELinux enforcing permanent
nano /etc/selinux/config
```

**Ensure this line:**
```
SELINUX=enforcing
```

**Save and exit**

---

### 7.2 Configure Firewall (Already done, but verify)

```bash
# List current firewall rules
firewall-cmd --list-all

# Should show:
# services: http https ssh
```

---

### 7.3 Secure File Permissions

```bash
# Lock down configuration files
chmod 600 /var/www/html/learning_platform/includes/db_connect.php
chmod 600 /var/www/html/learning_platform/composer.json
chmod 600 /var/www/html/learning_platform/composer.lock

# Ensure Apache can still read them
chown apache:apache /var/www/html/learning_platform/includes/db_connect.php
```

---

### 7.4 Disable PHP Functions (Optional but Recommended)

```bash
# Edit PHP configuration
nano /etc/php.ini
```

**Find and update:**
```ini
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

**Save, exit, and restart Apache:**
```bash
systemctl restart httpd
```

---

## ✅ STEP 8: Testing & Verification

### 8.1 Test Database Connection

```bash
# Create test script
nano /var/www/html/learning_platform/test_db.php
```

**Add this code:**
```php
<?php
require_once 'includes/db_connect.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✅ Database connection successful!<br>";
    echo "Total users: " . $result['count'] . "<br>";
    echo "PHP Version: " . phpversion() . "<br>";
    echo "✅ All systems operational!";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage();
}
?>
```

**Test in browser:**
- Navigate to: `http://server_ip/test_db.php`
- Should show "Database connection successful!"

**⚠️ DELETE the test file:**
```bash
rm -f /var/www/html/learning_platform/test_db.php
```

---

### 8.2 Test Application Login

**🌐 Open Browser:**
1. Navigate to: `http://server_ip/login.php`
2. You should see the login page with cyan/teal gradient

**Login with default admin:**
- Username: `admin.gov`
- Password: `admin123`

**✅ If successful:**
- You'll be redirected to admin dashboard
- Immediately change the password!

---

### 8.3 Verify File Uploads

```bash
# Check upload directories exist and are writable
ls -la /var/www/html/learning_platform/uploads/

# Should show directories owned by apache:apache
# videos/
# materials/
# posters/
# thumbnails/
# profile_pictures/
```

---

## 📤 STEP 9: Upload Content

### 9.1 Upload Video Files

**Using SCP from Windows:**
```powershell
# From folder containing videos
scp *.mp4 username@server_ip:/tmp/

# Then on server:
sudo mv /tmp/*.mp4 /var/www/html/learning_platform/uploads/videos/
sudo chown apache:apache /var/www/html/learning_platform/uploads/videos/*.mp4
```

**Or use SFTP client (WinSCP/FileZilla):**
- Connect to server
- Navigate to `/var/www/html/learning_platform/uploads/videos/`
- Upload video files

---

### 9.2 Upload PDF Materials

```bash
# Similar process for PDFs
# Upload to: /var/www/html/learning_platform/uploads/materials/

# Set permissions
chown apache:apache /var/www/html/learning_platform/uploads/materials/*
chmod 644 /var/www/html/learning_platform/uploads/materials/*
```

---

## 🔧 STEP 10: Post-Deployment Configuration

### 10.1 Change Default Admin Password

1. Login to admin panel
2. Go to profile settings
3. Change password to a strong password
4. Save changes

**Strong Password Requirements:**
- At least 12 characters
- Mix of uppercase, lowercase, numbers, symbols
- Example: `BankSec2025!@#Lrn`

---

### 10.2 Add Departments

1. Login as admin
2. Navigate to User Management
3. Add your bank's departments:
   - IT Security
   - Internal Audit
   - Compliance
   - Risk Management
   - etc.

---

### 10.3 Import Users (Bulk Upload)

**Option A: Manual Entry**
- Use admin panel → Add User button
- Fill in user details
- Set role as 'user'

**Option B: Bulk Upload via Excel**
1. Download template from admin panel
2. Fill in user details
3. Upload Excel file

**Option C: Direct SQL Import**
```bash
# Create CSV file with user data
nano /tmp/users.csv
```

**Format:**
```csv
username,password,first_name,last_name,staff_id,department_id,email,position
john.doe,TempPass123!,John,Doe,STF001,1,john.doe@bank.com,Security Analyst
jane.smith,TempPass123!,Jane,Smith,STF002,1,jane.smith@bank.com,IT Officer
```

**Import to database:**
```sql
LOAD DATA LOCAL INFILE '/tmp/users.csv' 
INTO TABLE users 
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"' 
LINES TERMINATED BY '\n' 
IGNORE 1 ROWS 
(username, @password, first_name, last_name, staff_id, department_id, email, position)
SET password = MD5(@password), 
    role = 'user', 
    status = 'active', 
    force_password_change = 1;
```

---

### 10.4 Configure Scheduled Tasks (Cron Jobs)

```bash
# Edit crontab for Apache user
crontab -e -u apache
```

**Add these cron jobs:**

```bash
# Database backup - Daily at 2 AM
0 2 * * * /usr/bin/mysqldump -u learning_platform_user -p'YourPassword' learning_platform_v6 > /backup/db_$(date +\%Y\%m\%d).sql

# Clean old session files - Daily at 3 AM
0 3 * * * find /var/lib/php/session -type f -mtime +7 -delete

# Clean old log files - Weekly on Sunday at 4 AM
0 4 * * 0 find /var/log/httpd -name "*.log" -type f -mtime +30 -delete
```

**Save and exit**

---

### 10.5 Setup Backup Directory

```bash
# Create backup directory
mkdir -p /backup/database
mkdir -p /backup/files

# Set permissions
chmod 700 /backup

# Create backup script
nano /usr/local/bin/backup_learning_platform.sh
```

**Add this script:**

```bash
#!/bin/bash
# Learning Platform Backup Script

BACKUP_DIR="/backup"
DB_USER="learning_platform_user"
DB_PASS="YourSecurePassword"
DB_NAME="learning_platform_v6"
APP_DIR="/var/www/html/learning_platform"
DATE=$(date +%Y%m%d_%H%M%S)

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/database/db_$DATE.sql.gz

# Backup files (uploads only)
tar -czf $BACKUP_DIR/files/uploads_$DATE.tar.gz $APP_DIR/uploads/

# Keep only last 30 days
find $BACKUP_DIR/database -type f -mtime +30 -delete
find $BACKUP_DIR/files -type f -mtime +30 -delete

echo "Backup completed: $DATE"
```

**Make it executable:**
```bash
chmod +x /usr/local/bin/backup_learning_platform.sh

# Test backup
/usr/local/bin/backup_learning_platform.sh

# Add to crontab
crontab -e
```

**Add:**
```bash
0 2 * * * /usr/local/bin/backup_learning_platform.sh >> /var/log/backup.log 2>&1
```

---

## 📊 STEP 11: Monitoring & Logs

### 11.1 Monitor Apache Logs

```bash
# Watch error log in real-time
tail -f /var/log/httpd/learning_platform_error.log

# Watch access log
tail -f /var/log/httpd/learning_platform_access.log

# Check for errors
grep -i error /var/log/httpd/learning_platform_error.log
```

---

### 11.2 Monitor MySQL Logs

```bash
# Check MySQL error log
tail -f /var/log/mariadb/mariadb.log

# Check slow queries
tail -f /var/log/mariadb/mariadb-slow.log
```

---

### 11.3 System Resource Monitoring

```bash
# Check disk usage
df -h

# Check memory usage
free -h

# Check CPU and processes
top

# Press 'q' to exit
```

---

## 🔍 STEP 12: Troubleshooting

### Issue 1: Cannot connect to database

**Solution:**
```bash
# Check if MariaDB is running
systemctl status mariadb

# Check database credentials
mysql -u learning_platform_user -p
# Enter password and see if you can connect

# Check SELinux boolean
getsebool httpd_can_network_connect_db
# Should be "on", if not:
setsebool -P httpd_can_network_connect_db 1
```

---

### Issue 2: File upload not working

**Solution:**
```bash
# Check directory permissions
ls -la /var/www/html/learning_platform/uploads/

# Should be owned by apache:apache
# If not:
chown -R apache:apache /var/www/html/learning_platform/uploads/
chmod -R 775 /var/www/html/learning_platform/uploads/

# Check SELinux context
ls -Z /var/www/html/learning_platform/uploads/
# Should show httpd_sys_rw_content_t

# If not:
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/learning_platform/uploads(/.*)?"
restorecon -Rv /var/www/html/learning_platform/uploads/
```

---

### Issue 3: 500 Internal Server Error

**Solution:**
```bash
# Check Apache error log
tail -50 /var/log/httpd/learning_platform_error.log

# Check PHP error log
tail -50 /var/log/php-fpm/www-error.log

# Common causes:
# 1. Syntax error in PHP files
# 2. Missing file permissions
# 3. SELinux blocking access

# Test Apache configuration
httpd -t

# Restart Apache
systemctl restart httpd
```

---

### Issue 4: Page not found (404)

**Solution:**
```bash
# Check if .htaccess is being read
# Enable AllowOverride in Apache config

nano /etc/httpd/conf.d/learning_platform.conf

# Ensure this line exists:
# AllowOverride All

# Restart Apache
systemctl restart httpd
```

---

### Issue 5: SELinux blocking access

**Check SELinux denials:**
```bash
# View recent denials
ausearch -m avc -ts recent

# Temporarily disable SELinux to test (NOT for production!)
# setenforce 0

# Fix SELinux instead:
# Create custom policy or adjust booleans
```

---

## 📋 Maintenance Checklist

### Daily
- [ ] Check application accessibility
- [ ] Monitor error logs for issues
- [ ] Verify backup completed

### Weekly
- [ ] Review system resource usage (CPU, RAM, Disk)
- [ ] Check for failed login attempts
- [ ] Review user activity

### Monthly
- [ ] Update system packages: `dnf update -y`
- [ ] Review and archive old logs
- [ ] Test backup restoration
- [ ] Review user accounts (remove inactive)

### Quarterly
- [ ] Security audit
- [ ] Performance optimization
- [ ] Capacity planning review

---

## 🎓 Training for Administrators

### Initial Setup Completed! Next Steps:

1. **Train IT Staff:**
   - How to add users
   - How to upload modules
   - How to manage content
   - How to run reports

2. **Create Documentation:**
   - User manual for staff
   - Admin manual for IT
   - Video tutorials

3. **Pilot Testing:**
   - Select 20-50 users for pilot
   - Gather feedback
   - Fix issues before full rollout

4. **Full Deployment:**
   - Add all 500 users
   - Send announcement email
   - Provide training sessions
   - Monitor adoption

---

## 📞 Support Information

### For Technical Issues:

**Server Issues:**
- Check logs: `/var/log/httpd/learning_platform_error.log`
- Database logs: `/var/log/mariadb/mariadb.log`
- Application logs: Check admin dashboard

**Quick Commands:**
```bash
# Restart Apache
systemctl restart httpd

# Restart MariaDB
systemctl restart mariadb

# Check all services
systemctl status httpd mariadb firewalld
```

---

## ✅ Final Verification Checklist

Before going live with 500 users:

- [ ] Admin login works
- [ ] User login works
- [ ] Video playback works
- [ ] Quiz functionality works
- [ ] File uploads work
- [ ] Database queries are fast (<500ms)
- [ ] Backups are automated and working
- [ ] Monitoring is in place
- [ ] Security hardening complete
- [ ] SSL certificate installed (if applicable)
- [ ] Firewall rules tested
- [ ] Documentation complete
- [ ] IT staff trained
- [ ] Pilot test successful

---

## 🎉 Congratulations!

Your Learning Platform is now deployed on Red Hat Enterprise Linux!

**Access URLs:**
- **User Portal:** `http://your_server_ip/login.php`
- **Admin Portal:** `http://your_server_ip/admin/login.php`

**Default Admin Credentials:**
- Username: `admin.gov`
- Password: `admin123` (CHANGE IMMEDIATELY!)

---

**Document Version:** 1.0  
**Created:** October 28, 2025  
**Platform Version:** 6.0  
**Target OS:** Red Hat Enterprise Linux 8/9
