# File Sharing Script

Modern and secure PHP file sharing script. Uploads large files in chunks, includes password protection and admin panel.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)

[Türkçe](README.md) | [English](README.en.md) 

## Features

- **Chunk Upload** - Uploads large files in 25MB chunks (up to 10GB)
- **Password Protection** - Protect files with passwords
- **Admin Panel** - File management, statistics, and bulk delete
- **Modern Design** - Dark theme, responsive and user-friendly interface
- **Drag & Drop** - Upload files by dragging and dropping
- **Progress Bar** - Shows upload speed and remaining time
- **Security** - CSRF protection, XSS protection, dangerous extension blocking
- **SEO Control** - Homepage is indexed, download pages are not

## Installation

### 1. Upload Files
Upload all files to your server.

### 2. Configure Settings
Edit `config.php`:

```php
define('SITE_NAME', 'File Share');
define('SITE_URL', 'https://yourdomain.com'); // Your domain
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'your_strong_password'); // Set a strong password
```

### 3. Folder Permissions
Give write permissions to these folders (chmod 755 or 775):

```
files/
data/
temp/
```

### 4. PHP Settings (Optional)
Create `.user.ini` or `php.ini` for large file uploads:

```ini
upload_max_filesize = 10G
post_max_size = 10G
max_execution_time = 7200
memory_limit = 512M
```

## Usage

### Uploading Files
1. Go to the homepage
2. Drag and drop a file or click to select
3. Optionally set a password
4. Click "Upload" button
5. Copy the download link

### Admin Panel
1. Go to `/admin.php`
2. Login with username and password
3. View files, delete them, or copy links

## Security Features

- **CSRF Token** - Form security
- **XSS Protection** - Output sanitization
- **Dangerous Extension Blocking** - PHP, htaccess, etc. files are blocked
- **Filename Sanitization** - Safe file names
- **Header Injection Protection** - Download header security
- **Session Security** - HttpOnly, Secure cookie settings

## Requirements

- PHP 7.4 or higher
- Apache (mod_rewrite enabled)
- Write permissions (files, data, temp folders)

## License

MIT License - Free to use and modify.

## Developer

Made by [Emir Can](https://emircan.tr)
