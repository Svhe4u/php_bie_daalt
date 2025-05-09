# Student Feedback System

A web-based system for managing student course evaluations, built with PHP and MySQL.

## Features

- User roles (Admin, Teacher, Student)
- Course management
- Student enrollment
- Course evaluations with ratings and comments
- Detailed statistics and reports

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/Svhe4u/php_bie_daalt.git
```

2. Import the database:
```bash
mysql -u root -p < database.sql
```

3. Configure the database connection:
   - Open `db.php`
   - Update the database credentials if needed:
```php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'student_feedback';
```

4. Set up your web server to point to the project directory

5. Access the system:
   - URL: `http://localhost/student_feedback_project`
   - Default admin credentials:
     - Email: admin@system.com
     - Password: admin123

## Usage

### Admin
- Manage users (add/edit/delete)
- Manage courses
- View all evaluations
- View system statistics

### Teacher
- View assigned courses
- View course evaluations
- Manage student enrollments

### Student
- View enrolled courses
- Submit course evaluations
- View submitted evaluations 