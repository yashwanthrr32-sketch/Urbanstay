# UrbanStay - PG Management System

A full stack web application for managing Paying Guest (PG) accommodations with multi-role access (Admin, Manager, Tenant, Parent) providing centralized control over PG operations.

---

## 📌 Project Overview

UrbanStay is a complete PG accommodation management platform that connects:

- 👑 **Admin** - System administrator with full control
- 🏢 **PG Manager** - Manages PG properties, tenants, and attendance
- 👤 **Tenant** - Books PG, makes payments, raises complaints
- 👪 **Parent** - Monitors child's accommodation and pays rent

---

## ✨ Features

| Role | Features |
|------|----------|
| **Admin** | Approve managers, manage PG listings, view all tenants, payment overview |
| **Manager** | Add PG, manage rooms/beds, approve bookings, mark attendance, verify payments |
| **Tenant** | Browse PGs, book beds, make UPI payments, give feedback, request vacate |
| **Parent** | View child details, track attendance, pay rent |

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP |
| Database | MySQL |
| Server | XAMPP / Apache |

---

## 💻 System Requirements

- XAMPP (v3.3.0 or higher)
- PHP 7.4+
- MySQL 5.7+
- Web Browser (Chrome/Firefox/Edge)

---

## 🚀 How to Run the Project

### Step 1: Install XAMPP
Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)

### Step 2: Start Services
Open XAMPP Control Panel → Start **Apache** and **MySQL**

### Step 3: Copy Project
Copy the `UrbanStay` folder to:C:\xampp\htdocs\UrbanStay


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
```
## Step 6: Run the Application
Open browser → http://localhost/UrbanStay

## 🔐 Default Admin Login

| email | password |
|-------|-------|
| admin@urbanstay.com | admin123 |


---

## 📂 Folder Structure
```php
UrbanStay/
├── index.php # Home page
├── pg-detail.php # PG detail page
├── auth/ # Authentication files
│ ├── login.php
│ ├── register.php
│ ├── forgot-password.php
│ └── logout.php
├── admin/ # Admin module
│ ├── dashboard.php
│ ├── managers.php
│ ├── tenants.php
│ ├── pg-listings.php
│ ├── complaints.php
│ └── payments.php
├── manager/ # Manager module
│ ├── dashboard.php
│ ├── tenants.php
│ ├── rooms.php
│ ├── attendance.php
│ ├── payments.php
│ ├── complaints.php
│ ├── pg-images.php
│ └── pg-settings.php
├── tenant/ # Tenant module
│ ├── dashboard.php
│ ├── booking.php
│ ├── payment.php
│ ├── attendance.php
│ ├── complaints.php
│ ├── feedback.php
│ └── profile.php
├── parent/ # Parent module
│ ├── dashboard.php
│ ├── children.php
│ ├── attendance.php
│ ├── payment.php
│ └── profile.php
├── assets/ # CSS, JS, Images
│ ├── css/
│ │ └── style.css
│ ├── js/
│ │ └── main.js
│ └── images/
│ └── uploads/
├── config/ # Database configuration
│ └── db.php
├── includes/ # Header, Footer, Navbar
│ ├── header.php
│ ├── footer.php
│ ├── navbar.php
│ └── session-check.php
├── ajax/ # AJAX handlers
└── urbanstay.sql # Database file
```
## 📧 Contact

For any queries: yashwanthrr32@gmail.com
