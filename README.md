# Posture and Fall Detection Dashboard

## 📌 Project Overview
This project is a comprehensive Posture and Fall Detection System featuring a web-based dashboard UI. It integrates a computer vision module to monitor posture and detect falls in real-time, alongside a PHP-based web portal for user registration, profile management, and system control.

## ⚙️ Prerequisites
To run this project locally, you will need the following installed on your machine:
* **XAMPP** (or any standard LAMP/WAMP stack) for the Apache server and MySQL database.
* **Python 3.x** (for the computer vision and posture detection modules).
* A modern web browser.

## 🗄️ Database Setup
The dashboard relies on a MySQL database to store user profiles and system logs.

1. Open **XAMPP Control Panel** and start the **Apache** and **MySQL** modules.
2. Open your web browser and go to `http://localhost/phpmyadmin`.
3. Click on **New** in the left sidebar to create a new database. Name it `goodlife_vision`.
4. Select the newly created `goodlife_vision` database, then click the **Import** tab at the top.
5. Click **Choose File** and select the `goodlife_vision_database.sql` file located in the root of this repository.
6. Scroll down and click **Import** (or **Go**) to set up the tables.

## 🚀 How to Run the System

### 1. Web Dashboard
1. Move the entire project folder into your XAMPP `htdocs` directory (usually located at `C:\xampp\htdocs\`).
2. Open your web browser and navigate to `http://localhost/Posture-Detection-System-/goodlife/register.php` (or the appropriate starting page) to access the UI.

### 2. Posture Detection Module
The Python vision scripts can be triggered via the dashboard (using `start_python.php`) or run manually from the terminal to initiate the camera feed and detection algorithms. 

## 📁 File Structure Highlights
* `/goodlife/` - Contains the core PHP files for the dashboard (registration, password reset, etc.).
* `/goodlife/uploads/` - Stores user profile assets.
* `goodlife_vision_database.sql` - The database export file required to run the portal.
