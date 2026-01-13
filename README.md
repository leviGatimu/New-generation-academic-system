# 🎓 Academic Bridge (New Generation Academy)

![PHP](https://img.shields.io/badge/Backend-PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/Database-MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![CSS3](https://img.shields.io/badge/UI-Glassmorphism%20CSS-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/Frontend-Vanilla%20JS-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Status](https://img.shields.io/badge/Status-Active%20Development-success?style=for-the-badge)

> **A modern, full-stack School Management System built for the Rwandan education sector.** > Features specific localization (RWF currency), a "WhatsApp-style" messaging system, and a premium Glassmorphism UI with fully persistent Dark Mode.

---

## 📸 Screenshots & Features
<img width="1905" height="893" alt="image" src="https://github.com/user-attachments/assets/faa27dd4-f4b1-4d7d-974e-f5eda08e8f01" />
<img width="1919" height="908" alt="image" src="https://github.com/user-attachments/assets/c908799a-cd97-46f1-9eb2-c170547a4162" />
<img width="1890" height="892" alt="Screenshot 2026-01-13 022719" src="https://github.com/user-attachments/assets/db966378-fc8a-41f1-86d8-4188bbb14106" />
<img width="1904" height="898" alt="image" src="https://github.com/user-attachments/assets/aa9cceae-994e-4cf1-a03c-f1911c155426" />
<img width="1916" height="903" alt="image" src="https://github.com/user-attachments/assets/a97d0766-bb0a-4468-a95d-5b5f8cd8a943" />






### 1. 🌓 **Smart Dark/Light Mode**
A custom JavaScript engine (`theme.js`) detects user preference and saves it to LocalStorage. It persists across all pages—from the Login screen to the Report Card generator.
- **Tech:** CSS Variables (`:root` vs `[data-theme="dark"]`) & Vanilla JS.

### 2. 👨‍👩‍👧‍👦 **Parent Portal (The "Super App")**
Parents get a comprehensive dashboard to manage their child's academic life without visiting the school.
- **Financial Tracking:** Real-time view of School Fees (Paid vs Owed in RWF) with visual indicators for overdue payments and equipment loans.
- **Child Switching:** One parent account can link to multiple students via unique **Access Codes**.
- **Report Cards:** Auto-generated, printable A4 report cards with grades and signatures.

### 3. 💬 **Real-Time Teacher-Parent Messaging**
A built-in chat interface that mimics modern social apps.
- **Blue/Orange Bubbles:** Distinct visual styling for Sender vs Receiver.
- **Teacher List:** Auto-fetched contact list for all teachers assigned to the student's class.

### 4. 📚 **Homework & Holiday Packages**
- **Teachers:** Can upload assignments (PDF/Word) via a secure portal.
- **Parents:** Can view and download holiday packages directly from their dashboard.

---

## 🛠️ Tech Stack & "Cool" Code

### **Frontend (The Visuals)**
We skipped heavy frameworks to build a blazing fast, custom UI.
* **Glassmorphism Header:** A semi-transparent, blurred navigation bar (`backdrop-filter: blur(12px)`) that floats over content.
* **CSS Grid & Flexbox:** Fully responsive layouts for dashboards and fee grids.
* **Interactive Hover Effects:** Buttons and cards lift (`transform: translateY(-2px)`) and glow on hover.

### **Backend (The Logic)**
* **Core:** Native PHP (No frameworks) for maximum control and performance on local XAMPP servers.
* **Database:** MySQL with relational tables linking `Parents` ↔ `Students` ↔ `Teachers` ↔ `Classes`.
* **Security:** Password hashing (`password_verify`), PDO prepared statements (SQL Injection protection), and Session-based role authentication.

---

## 🚀 Installation (Run it Locally)

1.  **Clone the Repo**
    ```bash
    git clone git@github.com:leviGatimu/New-generation-academic-system.git
    ```
2.  **Move to XAMPP**
    Place the folder inside `C:\xampp\htdocs\new_generation_academy`.
3.  **Database Setup**
    * Open `phpMyAdmin`.
    * Create a database named `nga_db`.
    * Import the `database.sql` file provided in the repo.
4.  **Create Uploads Folder**
    Create a folder named `uploads` in the root directory for homework files.
5.  **Run**
    Open your browser and visit: `http://localhost/new_generation_academy`

---

## 🔮 Future Roadmap
- [ ] **AI Chatbot:** Integration with Gemini API for student homework help.
- [ ] **Mobile App:** Wrapper for the Parent Portal.
- [ ] **Payment Gateway:** Integration with MoMo (MTN Mobile Money) API.

---

**Developed by Levi Gatimu** *Excellence in Technology & Arts*
