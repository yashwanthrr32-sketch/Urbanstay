# UrbanStay - PG Management System

A full stack web application for managing Paying Guest (PG) accommodations with multi-role access (Admin, Manager, Tenant, Parent) providing centralized controlove r PG operations.



## Project Overview

UrbanStay is a complete PG accommodation management platform that connects:
-  Admin - System administrator with full control
-  PG Manager - Manages PG properties, tenants, and attendance
-  Tenant - Books PG, makes payments, raises complaints
-  Parent - Monitors child's accommodation and pays rent



##  Features

 Role  Features 

 Admin- Approve managers, manage PG listings, view all tenants, payment overview 
 Manager-  Add PG, manage rooms/beds, approve bookings, mark attendance, verify payments 
 Tenant- Browse PGs, book beds, make UPI payments, give feedback, request vacate 
 Parent- View child details, track attendance, pay rent 



##  Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP |
| Database | MySQL |
| Server | XAMPP / Apache |



##  System Requirements

- XAMPP (v3.3.0 or higher)
- PHP 7.4+
- MySQL 5.7+
- Web Browser (Chrome/Firefox/Edge)

---

##  How to Run the Project

### Step 1: Install XAMPP
Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)

### Step 2: Start Services
Open XAMPP Control Panel → Start **Apache** and **MySQL**

### Step 3: Copy Project
Copy the `UrbanStay` folder to: C:\xampp\htdocs\UrbanStay

### Step 4: Create Database
1. Open browser → `http://localhost/phpmyadmin`
2. Create database: `urbanstay_db`
3. Import `urbanstay.sql` file

### Step 5: Configure Database
Edit `config/db.php`:
```php
$host = 'localhost';
$dbname = 'urbanstay_db';
$username = 'root';
$password = '';

### Step 6: Run the Application
Open browser → http://localhost/UrbanStay

## Default Admin Login
Email	- admin@urbanstay.com
Password	- admin123

UrbanStay/
├── index.php              # Home page
├── admin/                 # Admin module
├── manager/               # Manager module
├── tenant/                # Tenant module
├── parent/                # Parent module
├── assets/                # CSS, JS, Images
├── config/                # Database configuration
└── urbanstay.sql          # Database file

Contact:
For any queries: yashwanthrr32@gmail.com

           This project is developed for educational purposes as a BCA final year project.
