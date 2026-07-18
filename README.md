<div align="center">

# 🎓 Virtual Varsity

### A Modern Role-Based Learning Management System (LMS)

Build • Learn • Teach • Manage

<p>

[![Live Demo](https://img.shields.io/badge/🚀_Live_Demo-Visit-success?style=for-the-badge)](https://virtualvarsity.freedev.app/)
![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)
![Maintained](https://img.shields.io/badge/Maintained-Yes-brightgreen?style=for-the-badge)

</p>

---

### 🌐 Live Demo

## https://virtualvarsity.freedev.app/

</div>

---

# 📖 About

**Virtual Varsity** is a complete Learning Management System (LMS) built with **PHP, MySQL, JavaScript and Tailwind CSS**.

The platform provides separate dashboards for **Admin**, **Teacher**, and **Student**, making online education management simple and efficient.

It includes:

- 🎥 Live Classes
- ✅ Smart Attendance
- 📝 Live MCQ Exams
- 📄 Assignment Submission
- 📊 CGPA Tracking
- 📚 Course Management
- 👨‍🏫 Faculty Management
- 👨‍🎓 Student Portal

---

# 📱 Responsive Preview

| Desktop | Tablet | Mobile |
| ------- | ------ | ------ |
| ✅      | ✅     | ✅     |

---

# 📸 Screenshots

> Replace these placeholders with actual screenshots.

## 🏠 Home Page

```
assets/screenshots/home.png
```

<img src="assets/screenshots/home.png" width="100%"/>

---

## 👨‍💼 Admin Dashboard

<img src="assets/screenshots/admin-dashboard.png" width="100%"/>

---

## 👨‍🏫 Teacher Dashboard

<img src="assets/screenshots/teacher-dashboard.png" width="100%"/>

---

## 👨‍🎓 Student Dashboard

<img src="assets/screenshots/student-dashboard.png" width="100%"/>

---

# 🎥 Demo GIF

> Add a screen recording here.

```
assets/demo.gif
```

<img src="assets/demo.gif" width="100%"/>

---

# ✨ Features

## 👨‍💼 Admin

- 👨‍🎓 Student Management
- 👨‍🏫 Teacher Management
- 📚 Course Management
- 📈 GPA Management
- 🎓 Semester Promotion
- 🗃 Student Archive
- 📋 Enrollment Management
- 📊 Academic Records

---

## 👨‍🏫 Teacher

- 🎥 Start Live Class
- 🔑 Attendance Token
- 📝 Live MCQ
- 📄 PDF Assignment
- 📥 Export MCQ PDF
- 📊 View Student Performance
- 🗂 Archive MCQs

---

## 👨‍🎓 Student

- 📚 Enrolled Courses
- 🎥 Join Live Classes
- ✅ Attendance Verification
- 📝 MCQ Participation
- 📄 PDF Submission
- 📊 Attendance Report
- 🎓 CGPA View

---

# 🏗️ System Architecture

```mermaid
flowchart LR

A[Student]
B[Teacher]
C[Admin]

A --> D[(PHP Application)]
B --> D
C --> D

D --> E[(MySQL Database)]

B --> F[Live Class]
F --> A

B --> G[MCQ]
G --> A

B --> H[Assignments]
H --> A

C --> I[Course Management]
C --> J[Student Management]
C --> K[Teacher Management]
```

---

# 🧩 Modules

```
Authentication
│
├── Admin Portal
├── Teacher Portal
└── Student Portal

Academic
│
├── Courses
├── GPA
├── CGPA
└── Attendance

Assessment
│
├── Live MCQ
├── PDF Assignment
└── Quiz Submission

Administration
│
├── Users
├── Teachers
├── Students
└── Reports
```

---

# 🛠 Tech Stack

| Technology   | Used |
| ------------ | ---- |
| PHP          | ✅   |
| MySQL        | ✅   |
| Tailwind CSS | ✅   |
| JavaScript   | ✅   |
| HTML5        | ✅   |
| CSS3         | ✅   |
| mPDF         | ✅   |

---

# 📂 Project Structure

```
Virtual-Varsity
│
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── index.css
│   │   ├── student.css
│   │   ├── teacher.css
│   │   └── syllabus.css
│   └── data/
│       └── syllabus-data.json
├── backend/
│   ├── db.php
│   ├── attendance_helpers.php
│   ├── api/
│   │   ├── delete_old_mcq.php
│   │   ├── generate_mcq_pdf.php
│   │   ├── get_live_classes.php
│   │   ├── live_status.php
│   │   ├── start_attendance.php
│   │   └── update_live_status.php
│   └── setup/
│       ├── fresh_setup.php
│       ├── migrate_admin_v2.php
│       └── setup.php.old
├── uploads/
├── vendor/
├── admin_dashboard.php
├── index.php
├── student_dashboard.php
├── teacher_dashboard.php
├── syllabus.html
├── composer.json
├── composer.lock
└── README.md
```

---

# ⚙️ Installation

## Clone

```bash
git clone https://github.com/yourusername/virtual-varsity.git
```

---

## Enter Project

```bash
cd virtual-varsity
```

---

## Install Composer Packages

```bash
composer install
```

---

## Create Database

```
virtual_university
```

---

## Configure Database

Edit

```
db.php
```

---

## Generate Tables

Run

```
fresh_setup.php
```

or

```
http://localhost/virtual-varsity/fresh_setup.php
```

---

## Start Project

```
http://localhost/virtual-varsity/
```

---

# 👥 User Roles

| Role       | Access                         |
| ---------- | ------------------------------ |
| 👨‍💼 Admin   | Full Control                   |
| 👨‍🏫 Teacher | Course & Live Class Management |
| 👨‍🎓 Student | Learning Portal                |

---

# 📦 Dependencies

```json
{
  "mpdf/mpdf": "^8.3"
}
```

Install manually

```bash
composer require mpdf/mpdf
```

---

# 🚀 Upcoming Features

- 🔔 Notifications
- 📧 Email Verification
- 🔑 Password Reset
- 📱 Progressive Web App
- 💬 Live Chat
- 📹 Video Recording
- 📊 Analytics Dashboard
- 📡 REST API
- 📲 Android App

---

# 🤝 Contributing

```bash
Fork 🍴

↓

Create Branch 🌿

↓

Commit Changes 💻

↓

Push 🚀

↓

Create Pull Request 🎉
```

---

# ⭐ Support

If you like this project,

## ⭐ Star this Repository

It helps others discover the project.

---

# 📜 License

Distributed under the **MIT License**.

---

<div align="center">

## 👨‍💻 Developed By

**Dween Mohammad | Nazerin Kabir | Tahmina Bahar Khan**

🌐 https://github.com/dweenmd/ <br>
🌐 https://github.com/nazerindisha/ <br>
🌐 https://github.com/PROME24

---

### ⭐ Thanks for visiting ⭐

Made with using PHP & Tailwind CSS

</div>
