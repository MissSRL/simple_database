# Simple Database Manager

A secure, web-based MySQL database management tool designed for developers and database administrators. Built with PHP and featuring a modern dark green theme, this application provides an intuitive interface for database operations while maintaining enterprise-level security standards.

?? **[Live Demo](https://5earle.com/database)** | ?? [Documentation](#-installation--setup) | ?? [Quick Start](#quick-start)

![Database Manager Screenshot](assets/images/screenshot.png)

## ? Features

### ?? Security First
- **CSRF Protection**: All forms protected with secure tokens
- **Rate Limiting**: Prevents brute force attacks (10 attempts per 15 minutes)
- **Input Sanitization**: XSS and SQL injection protection
- **Session Security**: Automatic session regeneration and secure cookie settings
- **No Credential Storage**: Database credentials stored only in secure PHP sessions
- **Security Headers**: Comprehensive .htaccess security configuration

### ?? Database Operations
- **Multi-Table Management**: View, browse, and manage multiple database tables
- **Query Builder**: Interactive SQL query interface with syntax highlighting
- **Data Insertion**: Add new records with form validation
- **Table Browsing**: Paginated table data with column information
- **Real-time Previews**: Preview queries before execution

### ?? User Interface
- **Dark Theme**: Professional dark green color scheme
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Bootstrap 5**: Modern, accessible UI components
- **Real-time Feedback**: Success/error messages and loading states
- **Intuitive Navigation**: Clean sidebar and menu system

### ?? Performance
- **Efficient Pagination**: Handle large datasets with smart pagination
- **Connection Pooling**: Optimized database connection management
- **Minimal Dependencies**: Lightweight with essential features only

## ??? Installation & Setup

### Prerequisites
- **PHP 7.4+** with PDO MySQL extension
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Web Server** (Apache/Nginx) with mod_rewrite support
- **HTTPS** (recommended for production)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/MissSRL/simple_database.git
   cd simple_database
   ```

2. **Upload to your web server**
   - Copy all files to your web root directory
   - Ensure the web server has read/write permissions for session handling

3. **Configure your web server**
   - **Apache**: The included `.htaccess` file handles URL rewriting and security headers
   - **Nginx**: Configure URL rewriting manually (see nginx.conf example below)

4. **Access the application**
   - Navigate to your domain/subdirectory in a web browser
   - Enter your MySQL database credentials
   - Start managing your database!

> ?? **Try it first**: Check out the [live demo](https://5earle.com/database) to see the application in action before installing.

### Nginx Configuration Example
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

## ?? Security Features

### Built-in Protections
- **CSRF Tokens**: Every form submission requires a valid CSRF token
- **Rate Limiting**: IP-based connection attempt limiting
- **Input Validation**: All user inputs are sanitized and validated
- **SQL Injection Prevention**: Prepared statements for all database queries
- **XSS Protection**: HTML entity encoding for all output
- **Session Security**: Secure session handling with regeneration

### Deployment Security Checklist
- [ ] Enable HTTPS in production
- [ ] Use strong database passwords
- [ ] Restrict file permissions (644 for files, 755 for directories)
- [ ] Keep PHP and dependencies updated
- [ ] Monitor access logs for suspicious activity
- [ ] Consider IP whitelisting for admin access

### Security Headers (via .htaccess)
```apache
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
```

## ?? Usage Guide

### Connecting to a Database
1. Enter your MySQL server details:
   - **Host**: Database server hostname (usually `localhost`)
   - **Port**: MySQL port (default: `3306`)
   - **Username**: Your MySQL username
   - **Password**: Your MySQL password
   - **Database**: The database name to connect to

2. Click "Connect to Database"

### Managing Tables
- **View Tables**: Browse all tables in the connected database
- **Table Data**: Click any table name to view its contents
- **Pagination**: Navigate through large datasets with page controls
- **Column Info**: View column names, types, and constraints

### Running Queries
1. Navigate to "Query Builder"
2. Enter your SQL query in the editor
3. Use "Preview Query" to validate syntax
4. Execute with "Run Query"

### Inserting Data
1. Select a table from the sidebar
2. Click "Insert New Record"
3. Fill in the form fields
4. Submit to add the new record

## ??? Architecture

### File Structure
```
simple-database-manager/
??? api/
?   ??? database.php          # Database API endpoints
??? assets/
?   ??? css/
?   ?   ??? style.css         # Custom styling and dark theme
?   ??? js/
?       ??? app.js           # Main application JavaScript
?       ??? query.js         # Query builder functionality
?       ??? view.js          # Table view interactions
??? includes/
?   ??? config.php           # Configuration settings
?   ??? functions.php        # Core PHP functions
?   ??? get_columns.php      # Column information API
?   ??? preview_query.php    # Query preview functionality
??? index.php                # Main entry point
??? view.php                 # Table viewing interface
??? query.php                # Query builder interface
??? insert.php               # Data insertion interface
??? .htaccess                # Apache configuration
??? README.md                # This file
```

### Technology Stack
- **Backend**: PHP 7.4+ with PDO
- **Frontend**: Bootstrap 5, Vanilla JavaScript
- **Database**: MySQL/MariaDB
- **Security**: CSRF tokens, rate limiting, input sanitization
- **Styling**: Custom CSS with dark green theme

## ?? Use Cases

### Development Teams
- **Local Development**: Quick database browsing during development
- **Debugging**: Execute queries to troubleshoot issues
- **Data Exploration**: Understand database structure and relationships
- **Testing**: Insert test data and verify database states

### Database Administrators
- **Quick Maintenance**: Perform routine database operations
- **Data Auditing**: Browse and verify data integrity
- **Emergency Access**: Lightweight alternative to heavy database tools
- **Training**: Teach SQL and database concepts

### Small Projects
- **Startups**: Cost-effective database management solution
- **Personal Projects**: Simple interface for hobby databases
- **Prototyping**: Rapid database interaction for proof-of-concepts
- **Client Demos**: Show database contents to stakeholders

## ??? Security Considerations

### Recommended for:
- ? Development environments
- ? Internal tools with trusted users
- ? Small teams with proper access controls
- ? Localhost/private network usage

### Not recommended for:
- ? Public-facing production environments without additional security
- ? Shared hosting without HTTPS
- ? Environments with untrusted users
- ? Critical production databases without backup procedures

## ?? Contributing

Contributions are welcome! Please follow these guidelines:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'Add amazing feature'`)
4. **Push** to the branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

### Development Setup
```bash
# Clone your fork
git clone https://github.com/MissSRL/simple_database.git

# Create a development branch
git checkout -b feature/your-feature-name

# Make your changes and test thoroughly

# Commit with descriptive messages
git commit -m "Add: new feature description"
```

### Code Standards
- Follow PSR-12 PHP coding standards
- Use meaningful variable and function names
- Add comments for complex logic
- Ensure all security features remain intact
- Test with multiple PHP versions

## ?? License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

```
MIT License

Copyright (c) 2025 5earle.com

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## ?? Support

### Getting Help
- **Live Demo**: [https://5earle.com/database](https://5earle.com/database)
- **Issues**: [GitHub Issues](https://github.com/MissSRL/simple_database/issues)
- **Discussions**: [GitHub Discussions](https://github.com/MissSRL/simple_database/discussions)
- **Documentation**: Check this README and inline code comments

### Common Issues

**Connection Failed**
- Verify database credentials
- Check if MySQL server is running
- Ensure PHP PDO MySQL extension is installed
- Verify network connectivity to database server

**Permission Denied**
- Check file permissions (644 for files, 755 for directories)
- Ensure web server has read access to application files
- Verify database user has necessary privileges

**Session Issues**
- Check PHP session configuration
- Ensure session directory is writable
- Verify session cookies are enabled in browser

## ?? Changelog

### Version 1.0.0 (2025-01-XX)
- ? Initial release
- ?? Comprehensive security implementation
- ?? Dark green theme
- ?? Responsive design
- ?? Core database management features

---

**Made with ?? by [5earle.com](https://5earle.com)**

*Simple Database Manager - Secure, intuitive, and powerful database management for everyone.*
