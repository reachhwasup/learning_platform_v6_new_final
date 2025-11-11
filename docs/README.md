# Information Security Learning Platform

**Version:** 6.0  
**Last Updated:** October 28, 2025

## Overview

A comprehensive web-based learning management system designed for Information Security Awareness Training. The platform provides video-based learning modules, assessments, progress tracking, and certificate generation for users.

---

## Features

### User Features
- 📚 **Module-based Learning** - Video lessons with progress tracking
- 📝 **Assessments & Quizzes** - Test knowledge with interactive quizzes
- 🎓 **Certificates** - Generate and download completion certificates
- 📊 **Progress Dashboard** - Track learning progress and completed modules
- 👤 **Profile Management** - Update personal information and profile picture
- 📄 **Learning Materials** - Download supplementary materials (PDFs)
- 🖼️ **Educational Posters** - View and download security awareness posters

### Admin Features
- 👥 **User Management** - Create, edit, and manage user accounts
- 📚 **Module Management** - Upload and manage video content and materials
- ❓ **Question Bank** - Create and manage assessment questions
- 📊 **Reports & Analytics** - View user progress and generate reports
- 🖼️ **Poster Management** - Upload and manage educational posters
- 📤 **Data Export** - Export user data and reports to Excel

---

## Technology Stack

### Backend
- **PHP** 7.4+ (with PDO for database operations)
- **MySQL** / MariaDB
- **FPDF** - PDF certificate generation
- **PhpSpreadsheet** - Excel export functionality

### Frontend
- **HTML5** / CSS3
- **JavaScript** (Vanilla JS)
- **Tailwind CSS** - Utility-first CSS framework

### Dependencies
- **Composer** - PHP dependency manager
- **phpoffice/phpspreadsheet** - Excel file generation
- **maennchen/zipstream-php** - ZIP archive creation

---

## Project Structure

```
learning_platform_v6/
│
├── admin/                          # Admin panel
│   ├── index.php                   # Admin dashboard
│   ├── login.php                   # Admin login
│   ├── manage_users.php            # User management
│   ├── manage_modules.php          # Module management
│   ├── manage_questions.php        # Question bank
│   ├── manage_video.php            # Video upload and details
│   ├── manage_posters.php          # Poster management
│   ├── reports.php                 # Analytics and reports
│   ├── view_user_progress.php      # User progress tracking
│   └── includes/                   # Admin includes
│       ├── header.php
│       ├── footer.php
│       ├── sidebar.php
│       └── auth_check.php
│
├── api/                            # API endpoints
│   ├── auth/                       # Authentication APIs
│   │   ├── user_login.php
│   │   ├── user_signup.php
│   │   ├── admin_login.php
│   │   └── logout.php
│   ├── admin/                      # Admin APIs
│   │   ├── user_crud.php
│   │   ├── module_crud.php
│   │   ├── question_crud.php
│   │   ├── video_crud.php
│   │   ├── poster_crud.php
│   │   ├── dashboard_data.php
│   │   ├── generate_report.php
│   │   ├── export_basic.php
│   │   └── export_user_details.php
│   ├── learning/                   # Learning APIs
│   │   ├── get_modules.php
│   │   ├── get_videos.php
│   │   ├── submit_quiz.php
│   │   ├── submit_assessment.php
│   │   └── track_progress.php
│   └── user/                       # User APIs
│       ├── update_profile.php
│       └── generate_certificate.php
│
├── assets/                         # Static assets
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   ├── main.js
│   │   ├── admin.js
│   │   └── video_player.js
│   ├── images/
│   │   ├── logo.png
│   │   ├── logo_blue.png
│   │   └── default_avatar.jpg
│   └── templates/
│       └── question_template.csv
│
├── includes/                       # Core includes
│   ├── db_connect.php             # Database connection
│   ├── auth_check.php             # User authentication
│   ├── header.php                 # User header
│   ├── footer.php                 # User footer
│   ├── user_sidebar.php           # User navigation
│   ├── functions.php              # Helper functions
│   └── lib/                       # Third-party libraries
│       ├── fpdf.php               # PDF generation
│       └── font/                  # FPDF fonts
│
├── uploads/                        # User uploads
│   ├── videos/                    # Video files
│   ├── materials/                 # PDF materials
│   ├── posters/                   # Poster images
│   ├── thumbnails/                # Video thumbnails
│   └── profile_pictures/          # User profile pictures
│
├── vendor/                         # Composer dependencies
│
├── index.php                       # User homepage
├── login.php                       # User login
├── dashboard.php                   # User dashboard
├── view_module.php                 # Video player and module view
├── materials.php                   # Learning materials
├── profile.php                     # User profile
├── posters.php                     # Educational posters
├── help_support.php                # Help and support
├── composer.json                   # Composer dependencies
├── composer.lock                   # Locked dependencies
└── README.md                       # This file
```

---

## Database Structure

### Database Name
`learning_platform_v6`

### Main Tables

#### 1. **users**
Stores user account information.
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- username (VARCHAR)
- password (VARCHAR, hashed)
- first_name (VARCHAR)
- last_name (VARCHAR)
- staff_id (VARCHAR)
- department_id (INT, FK to departments)
- email (VARCHAR)
- phone (VARCHAR)
- role (ENUM: 'user', 'admin')
- status (ENUM: 'active', 'inactive', 'locked')
- profile_picture (VARCHAR)
- created_at (DATETIME)
- updated_at (DATETIME)
```

#### 2. **departments**
Stores department/organization information.
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- name (VARCHAR)
- created_at (DATETIME)
```

#### 3. **modules**
Stores learning module information.
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- title (VARCHAR)
- description (TEXT)
- video_path (VARCHAR)
- thumbnail_path (VARCHAR)
- material_path (VARCHAR)
- duration (INT, in seconds)
- order_index (INT)
- is_active (BOOLEAN)
- created_at (DATETIME)
- updated_at (DATETIME)
```

#### 4. **questions**
Stores assessment questions.
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- module_id (INT, FK to modules)
- question_text (TEXT)
- option_a (VARCHAR)
- option_b (VARCHAR)
- option_c (VARCHAR)
- option_d (VARCHAR)
- correct_answer (ENUM: 'A', 'B', 'C', 'D')
- created_at (DATETIME)
```

#### 5. **user_progress**
Tracks user learning progress.
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- user_id (INT, FK to users)
- module_id (INT, FK to modules)
- video_completed (BOOLEAN)
- quiz_completed (BOOLEAN)
- assessment_completed (BOOLEAN)
- quiz_score (DECIMAL)
- assessment_score (DECIMAL)
- completed_at (DATETIME)
- updated_at (DATETIME)
```

#### 6. **posters**
Stores educational poster information.
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- title (VARCHAR)
- description (TEXT)
- image_path (VARCHAR)
- created_at (DATETIME)
```

#### 7. **certificates**
Stores certificate generation records.
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- user_id (INT, FK to users)
- completed_at (DATETIME)
- created_at (DATETIME)
```

---

## Installation

### Prerequisites
- **XAMPP** / WAMP / LAMP (Apache, MySQL, PHP 7.4+)
- **Composer** (PHP dependency manager)
- **Web Browser** (Chrome, Firefox, Edge)

### Steps

1. **Clone or download** the project to your web server directory:
   ```bash
   cd C:\xampp\htdocs\
   git clone <repository-url> learning_platform_v6
   ```

2. **Install PHP dependencies**:
   ```bash
   cd learning_platform_v6
   composer install
   ```

3. **Create the database**:
   - Open phpMyAdmin or MySQL client
   - Create a database named `learning_platform_v6`
   - Import the SQL schema (if provided)

4. **Configure database connection**:
   - Edit `includes/db_connect.php`
   - Update database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'learning_platform_v6';
     $username = 'root';
     $password = '';
     ```

5. **Set folder permissions**:
   - Ensure `uploads/` folder and subfolders are writable by the web server

6. **Access the application**:
   - User interface: `http://localhost/learning_platform_v6/`
   - Admin panel: `http://localhost/learning_platform_v6/admin/`

### Default Admin Credentials
```
Username: admin
Password: (set during installation or use default)
```

---

## Configuration

### File Upload Limits
To upload large video files, update `php.ini`:
```ini
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 300
```

### Video Formats Supported
- MP4 (recommended)
- WebM
- OGG

### Material Formats Supported
- PDF

---

## Usage

### For Users
1. **Login** with your credentials
2. **Browse modules** on the dashboard
3. **Watch videos** and complete quizzes
4. **Take assessments** to earn certificates
5. **Download certificates** from "My Certificates"

### For Admins
1. **Login** to admin panel
2. **Manage users** - Create, edit, delete user accounts
3. **Upload modules** - Add videos, materials, thumbnails
4. **Create questions** - Build question bank for assessments
5. **View reports** - Track user progress and generate analytics
6. **Export data** - Download user reports in Excel format

---

## Security Features

- ✅ Password hashing (bcrypt/password_hash)
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ Session-based authentication
- ✅ Role-based access control (user/admin)
- ✅ CSRF protection (recommended to add)

---

## Future Enhancements

- [ ] Multi-language support
- [ ] Email notifications
- [ ] Advanced analytics dashboard
- [ ] Mobile app integration
- [ ] Video streaming optimization
- [ ] Real-time chat support
- [ ] Gamification (badges, leaderboards)

---

## Support

For issues, questions, or feature requests, please contact:
- **Email:** support@example.com
- **Documentation:** [Link to docs]

---

## License

This project is proprietary software. All rights reserved.

---

## Credits

**Developed by:** [Your Name/Organization]  
**Year:** 2025  
**Framework:** Custom PHP MVC-inspired architecture  
**UI Design:** Tailwind CSS

