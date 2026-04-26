# 📁 Victor's File Uploading System (PHP)

## 📌 Overview

This project is a **simple file uploading web application** built using **PHP**.
It was developed as a **3rd-year Web Programming project** to demonstrate how files can be uploaded, stored, and managed on a server.

---

## 🚀 Features

* 📤 Upload files (images, documents, etc.)
* 📂 Store files on the server
* 👀 View uploaded files
* ❌ Basic validation (file type / size)
* 🧾 Simple and clean user interface

---

## 🧱 Technologies Used

* **PHP** (Core backend logic)
* **HTML & CSS** (Frontend)
* **MySQL** (Optional for storing file info)
* **XAMPP / WAMP** (Local server)

---

## 📂 Project Structure

```id="8j0l7t"
project-folder/
│
├── index.php        # Main upload page
├── upload.php       # File upload logic
├── uploads/         # Stored files
├── style.css        # Styling
└── config.php       # Database connection (optional)
```

---

## ⚙️ Setup Instructions

### 1. Install Local Server

Install:

* XAMPP or WAMP

---

### 2. Move Project

Copy the project folder to:

```id="8rmf7q"
htdocs/   (for XAMPP)
```

---

### 3. Start Server

* Start **Apache**
* Start **MySQL** (if using database)

---

### 4. Open in Browser

```id="d3jzba"
http://localhost/project-folder
```

---

## 📤 How It Works

1. User selects a file
2. Clicks upload
3. PHP processes the file
4. File is stored in `/uploads` folder
5. (Optional) File info saved in database

---

## 🔐 Basic Security

* File type checking
* File size limit
* Prevent duplicate file names

---

## 🛠️ Future Improvements

* User login system
* File download feature
* Drag & drop upload
* Better UI design

---

## 👨‍💻 Author

Victor — 3rd Year Computer Science Student

---

## 📄 License

This project is for educational purposes only.
