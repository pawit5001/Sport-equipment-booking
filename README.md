# 🏀 Sport Equipment Booking System

A web-based sport equipment borrowing and return management system for educational institutions. Built with PHP and MySQL.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)

---

## 📋 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Database Schema](#-database-schema)
- [Usage Guide](#-usage-guide)
- [Project Structure](#-project-structure)

---

## ✨ Features

### 👤 For Students (User)
- ✅ Register and login
- ✅ Browse available sport equipment
- ✅ Add equipment to cart
- ✅ Book equipment
- ✅ View booking history
- ✅ Print booking receipt
- ✅ Change password / Edit profile

### 👨‍💼 For Administrator (Admin)
- ✅ Manage equipment categories (Add/Edit/Delete)
- ✅ Manage sport equipment (Add/Edit/Delete)
- ✅ Manage suppliers
- ✅ Manage student data
- ✅ View all bookings
- ✅ Record equipment returns
- ✅ System settings (Max booking days, late fees)
- ✅ Dashboard with statistics
- ✅ Print booking receipts

---

## 💻 Requirements

| Item | Minimum Version |
|------|-----------------|
| PHP | 7.4 or higher |
| MySQL | 5.7 or higher |
| Web Server | Apache (XAMPP recommended) |
| Browser | Chrome, Firefox, Edge |

---

## 🚀 Installation

### Step 1: Install XAMPP
Download and install [XAMPP](https://www.apachefriends.org/download.html)

### Step 2: Clone the Project
```bash
cd C:\xampp\htdocs
git clone https://github.com/pawit5001/Sport-equipment-booking.git
```

### Step 3: Create Database
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Create a new database named `sports_equipment_booking`
3. Import SQL file (if available) or create tables according to [Database Schema](#-database-schema)

### Step 4: Configure Database Connection
Edit `includes/config.php`:
```php
<?php
$dbhost = "localhost";
$dbuser = "root";        // MySQL username
$dbpass = "";            // MySQL password (XAMPP default is empty)
$dbname = "sports_equipment_booking";
?>
```

### Step 5: Start the Application
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL**
3. Open Browser and go to: http://localhost/Sport-equipment-booking

---

## 🗄️ Database Schema

### All Tables (8 tables)

| Table | Description |
|-------|-------------|
| `tblmembers` | User data (Students + Admin) |
| `tblcategory` | Equipment categories |
| `tblequipment` | Sport equipment data |
| `tblequipment_pricing` | Equipment rental pricing |
| `tblsuppliers` | Supplier/PIC information |
| `tblbookings` | Booking records |
| `tblbookingdetails` | Booking details (each item) |
| `tblbooking_settings` | System settings |

### SQL Create Tables

```sql
-- Users table
CREATE TABLE tblmembers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    StudentID VARCHAR(20) NOT NULL,
    FullName VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    MobileNumber VARCHAR(15),
    Password VARCHAR(255) NOT NULL,
    Role ENUM('user', 'admin') DEFAULT 'user',
    Status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    RegDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE tblcategory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL,
    Status INT DEFAULT 1,
    CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment table
CREATE TABLE tblequipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    EquipmentName VARCHAR(100) NOT NULL,
    CategoryID INT,
    SupplierID INT,
    Quantity INT DEFAULT 0,
    AvailableQty INT DEFAULT 0,
    EquipmentImage VARCHAR(255),
    Description TEXT,
    Status ENUM('available', 'unavailable') DEFAULT 'available',
    RegDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CategoryID) REFERENCES tblcategory(id),
    FOREIGN KEY (SupplierID) REFERENCES tblsuppliers(id)
);

-- Suppliers table
CREATE TABLE tblsuppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    SupplierName VARCHAR(100) NOT NULL,
    ContactPerson VARCHAR(100),
    Phone VARCHAR(20),
    Email VARCHAR(100),
    Address TEXT,
    Status INT DEFAULT 1,
    CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE tblbookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    BookingID VARCHAR(50) NOT NULL UNIQUE,
    StudentID INT NOT NULL,
    BookingDate DATE NOT NULL,
    ReturnDate DATE,
    ActualReturnDate DATE,
    Status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    TotalItems INT DEFAULT 0,
    Notes TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (StudentID) REFERENCES tblmembers(id)
);

-- Booking details table
CREATE TABLE tblbookingdetails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    BookingID INT NOT NULL,
    EquipmentID INT NOT NULL,
    Quantity INT DEFAULT 1,
    ReturnedQty INT DEFAULT 0,
    Status ENUM('borrowed', 'returned', 'damaged', 'lost') DEFAULT 'borrowed',
    FOREIGN KEY (BookingID) REFERENCES tblbookings(id),
    FOREIGN KEY (EquipmentID) REFERENCES tblequipment(id)
);

-- Booking settings table
CREATE TABLE tblbooking_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    max_booking_days INT DEFAULT 7,
    late_fee_per_day DECIMAL(10,2) DEFAULT 10.00,
    max_items_per_booking INT DEFAULT 5,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO tblbooking_settings (max_booking_days, late_fee_per_day, max_items_per_booking) 
VALUES (7, 10.00, 5);

-- Create default Admin (password: admin123)
INSERT INTO tblmembers (StudentID, FullName, Email, Password, Role, Status) 
VALUES ('ADMIN001', 'Administrator', 'admin@example.com', MD5('admin123'), 'admin', 'active');
```

---

## 📖 Usage Guide

### 🔐 Login

#### For Students
- URL: `http://localhost/Sport-equipment-booking/`
- Register a new account or login with Email and Password

#### For Administrator
- URL: `http://localhost/Sport-equipment-booking/adminlogin.php`
- Email: `admin@example.com`
- Password: `admin123`

---

### 👤 Student Guide

#### 1. Borrowing Equipment
1. Login to the system
2. Click **"Book Equipment"** in the menu
3. Select category and desired equipment
4. Set quantity and click **"Add to Cart"**
5. Go to Cart → Review items → Click **"Confirm Booking"**
6. Select return date → Click **"Submit Booking"**

#### 2. View Booking History
1. Click **"My Bookings"** in the menu
2. View all booking status
3. Click **"Print Receipt"** to print

#### 3. Edit Profile
1. Click **"Profile"** in the menu
2. Edit personal information
3. Click **"Save"**

---

### 👨‍💼 Administrator Guide

#### 1. Manage Categories
- Go to **Manage Categories** → Add/Edit/Delete categories

#### 2. Manage Equipment
- Go to **Manage Equipment** → Add new equipment
- Fill in: Name, Category, Quantity, Image
- Status: Available / Unavailable

#### 3. View Bookings
1. Go to **Manage Bookings**
2. View all booking records
3. Click **"View Details"** to see booking information

#### 4. Record Returns
1. Go to **Manage Bookings**
2. Select the booking to record return
3. Click **"Record Return"**
4. Enter returned quantity and status (Normal/Damaged/Lost)

#### 5. System Settings
- Go to **Booking Settings**
- Configure: Max booking days, Late fee per day, Max items per booking

---

## 📁 Project Structure

```
Sport-equipment-booking/
├── index.php                    # Home page (Login)
├── signup.php                   # Registration
├── adminlogin.php               # Admin Login
├── dashboard.php                # Student Dashboard
├── book-equipment.php           # Book Equipment page
├── booking-checkout.php         # Cart/Checkout page
├── booking-confirmation.php     # Booking Confirmation
├── booking-receipt.php          # Booking Receipt
├── my-bookings.php              # Booking History
├── my-profile.php               # Profile
├── change-password.php          # Change Password
├── cart-actions.php             # Cart API
├── config.php                   # Main config file
│
├── admin/                       # Admin folder
│   ├── dashboard.php            # Admin Dashboard
│   ├── add-equipment.php        # Add Equipment
│   ├── edit-equipment.php       # Edit Equipment
│   ├── manage-equipment.php     # Manage Equipment
│   ├── add-category.php         # Add Category
│   ├── manage-categories.php    # Manage Categories
│   ├── add-supplier.php         # Add Supplier
│   ├── manage-suppliers.php     # Manage Suppliers
│   ├── manage-bookings.php      # Manage Bookings
│   ├── view-booking.php         # View Booking Details
│   ├── return-booking.php       # Record Return
│   ├── booking-settings.php     # Booking Settings
│   ├── reg-students.php         # Manage Students
│   └── includes/                # Admin Header/Footer
│
├── includes/                    # User Header/Footer
│   ├── config.php
│   ├── header.php
│   └── footer.php
│
├── assets/                      # CSS/JS/Images
│   ├── css/
│   ├── js/
│   └── img/
│
└── uploads/                     # Equipment Images
```

---

## 🔧 Troubleshooting

### ❌ Cannot connect to database
- Check if MySQL in XAMPP is running
- Verify database name in `includes/config.php`

### ❌ Page shows errors
- Enable error reporting in PHP
- Check PHP version >= 7.4

### ❌ Images not displaying
- Check if `uploads/` folder has write permissions
- Verify image paths

---

## 👥 Developer

- **Project Name**: Sport Equipment Booking System
- **GitHub**: [pawit5001/Sport-equipment-booking](https://github.com/pawit5001/Sport-equipment-booking)

---

## 📄 License

MIT License - Free to use and modify
