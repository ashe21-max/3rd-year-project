# 🏠 Housing Allocation System — File Uploading Project

## 📌 Overview

This project is a **full-stack web application** developed for a 3rd-year Web Programming course.
It focuses on **secure file uploading, user management, and housing allocation workflows**.

Users can register, log in, upload required documents, and interact with the system through a modern web interface.

---

## 🚀 Features

* 🔐 User Authentication (Login/Register)
* 📁 File Upload System (documents/images)
* 🏠 Housing Allocation Management
* 📡 API-based communication (Frontend ↔ Backend)
* ⚡ Fast and responsive UI
* 🛡️ Secure password handling

---

## 🧱 Tech Stack

### Frontend

* Next.js (React Framework)
* TypeScript
* Tailwind CSS

### Backend

* Node.js
* Express.js
* TypeScript

### Database

* PostgreSQL
* Drizzle ORM

---

## 🔄 System Architecture

Frontend (Next.js)
⬇
Backend API (Express.js)
⬇
Database (PostgreSQL)

---

## 📂 Project Structure

```
project-root/
│
├── client/        # Frontend (Next.js)
├── server/        # Backend (Express)
├── db/            # Database configuration
├── uploads/       # Uploaded files
└── package.json
```

---

## ⚙️ Installation & Setup

### 1. Clone the repository

```
git clone <your-repo-url>
cd HousingAllocationSystem
```

### 2. Install dependencies

#### Frontend

```
cd client
npm install
```

#### Backend

```
cd ../server
npm install
```

---

### 3. Setup Environment Variables

Create a `.env` file in `/server`:

```
DATABASE_URL=your_postgresql_connection
JWT_SECRET=your_secret_key
```

---

### 4. Run the Project

#### Start Backend

```
cd server
npm run dev
```

#### Start Frontend

```
cd client
npm run dev
```

---

## 🌐 Usage

1. Register a new account
2. Login to the system
3. Upload required files
4. View housing allocation status

---

## 📸 Screenshots

(Add your screenshots here)

---

## 🛠️ Future Improvements

* Admin dashboard
* File validation & size limits
* Email notifications
* Better UI/UX

---

## 👨‍💻 Author

Victor — Computer Science Student

---

## 📄 License

This project is for educational purposes.
