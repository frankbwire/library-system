# LibraryMS — Library Management System
## Version 1.0.0 | Built with PHP + MySQL + Bootstrap 5

---

## 📋 Overview
A fully functional web-based Library Management System to replace Excel-based workflows.
Manages books inventory, borrowers, borrowing/return transactions, and analytics.

---

## 🖥️ Requirements
- **XAMPP** (Windows) or **LAMP/MAMP** (Linux/Mac)
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Web browser (Chrome, Firefox, Edge)

---

## 🚀 Setup Instructions

### Step 1 — Copy Files
```
Copy the `library-system/` folder to your web server root:
  - XAMPP (Windows): C:\xampp\htdocs\library-system\
  - LAMP (Linux):    /var/www/html/library-system/
  - MAMP (Mac):      /Applications/MAMP/htdocs/library-system/
```

### Step 2 — Create Database
1. Open **phpMyAdmin**: http://localhost/phpmyadmin
2. Click **"New"** to create a database (or let the SQL script do it)
3. Click **"Import"** tab
4. Choose `database.sql` from the project root
5. Click **"Go"**

**OR via MySQL CLI:**
```bash
mysql -u root -p < database.sql
```

### Step 3 — Configure Database Connection
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');   // Usually 'localhost'
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password (empty for XAMPP default)
define('DB_NAME', 'library_db');  // Database name
```

### Step 4 — Set Base URL
Edit `config/app.php`:
```php
define('BASE_URL', 'http://localhost/library-system');
```
Change this if your setup uses a different port or path.

### Step 5 — Access the System
Open your browser and go to:
```
http://localhost/library-system
```

### Step 6 — Login
```
Username: admin
Password: Library@2024
```
⚠️ **Change the default password immediately after first login.**

---

## 📁 Project Structure
```
library-system/
├── config/
│   ├── app.php              # App settings (BASE_URL, timezone, session)
│   └── database.php         # Database connection (PDO)
├── includes/
│   ├── header.php           # HTML head + CSS links
│   ├── sidebar.php          # Navigation sidebar
│   ├── footer.php           # JS scripts
│   └── helpers.php          # Utility functions (sanitize, badge, paginate, etc.)
├── auth/
│   ├── login.php            # Login page
│   ├── logout.php           # Logout handler
│   └── hash.php             # Password hash generator utility
├── modules/
│   ├── books/
│   │   ├── index.php        # Books list (search, filter, paginate, delete)
│   │   ├── add.php          # Add new book
│   │   └── edit.php         # Edit book
│   ├── borrowers/
│   │   ├── index.php        # Borrowers list
│   │   ├── add.php          # Add borrower
│   │   ├── edit.php         # Edit borrower
│   │   └── history.php      # Borrower borrow history
│   ├── borrow/
│   │   ├── index.php        # All borrow records (filter, export, paginate)
│   │   ├── issue.php        # Issue (borrow) a book
│   │   └── return.php       # Return a book
│   └── reports/
│       ├── index.php        # Reports dashboard (5 report types)
│       └── export.php       # CSV export handler
├── assets/
│   ├── css/style.css        # Custom CSS (sidebar, cards, tables, print)
│   └── js/app.js            # DataTables init, sidebar toggle, confirm dialogs
├── database.sql             # Full database schema + sample data
├── index.php                # Entry point (redirects to dashboard or login)
└── dashboard.php            # Main dashboard with stats + Chart.js charts
```

---

## 🔐 Security Features
- **Password hashing**: PHP `password_hash()` with bcrypt
- **SQL injection prevention**: PDO prepared statements throughout
- **XSS prevention**: `htmlspecialchars()` on all output
- **Session-based auth**: All pages require valid session
- **Input validation**: Server-side validation on all forms
- **Audit logging**: All create/update/delete/borrow/return actions logged

---

## 📊 Features Summary

### Books Module
- Add, edit, delete books
- Search by title, author, Book ID
- Filter by category and status
- CSV export + print
- Auto-generate suggested Book IDs

### Borrowers Module
- Add, edit, delete borrowers
- View full borrow history per borrower
- Category-based ID suggestions (STU-, TCH-, STF-)
- Filter by category and status

### Borrow & Return
- Issue books (only Available books can be borrowed)
- Automatic book status update on borrow/return
- Overdue detection (> 14 days, configurable via `BORROW_DAYS`)
- Due date auto-calculated on issue form

### Dashboard
- 7 live stat cards
- Bar chart: Borrow trend (last 6 months)
- Doughnut chart: Books by category
- Top 10 borrowed books table
- Overdue books alert list
- Recent transactions

### Reports (5 types)
1. **Borrow Records** — filterable by date, book category, borrower category, status
2. **All Books** — inventory report with filters
3. **Overdue Books** — all unreturned books past due date
4. **Most Borrowed Books** — popularity ranking
5. **Most Active Borrowers** — borrower activity ranking

### Export
- CSV export with UTF-8 BOM (opens correctly in Excel)
- Print-optimized layout (hides navigation/buttons)

---

## ⚙️ Configuration

### Change Loan Period
In `config/app.php`:
```php
define('BORROW_DAYS', 14); // Change to any number
```

### Add New Admin User
1. Visit `http://localhost/library-system/auth/hash.php?p=YourNewPassword`
2. Copy the hash
3. Insert into `users` table via phpMyAdmin:
```sql
INSERT INTO users (username, password, full_name, role)
VALUES ('newadmin', 'PASTE_HASH_HERE', 'New Admin', 'admin');
```
4. **Delete `auth/hash.php`** when done.

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page / errors | Enable PHP error display: add `ini_set('display_errors', 1);` to `config/app.php` |
| "DB connection failed" | Check `config/database.php` credentials |
| Login fails | Regenerate hash using `auth/hash.php` and update DB |
| CSS not loading | Check `BASE_URL` in `config/app.php` |
| Session not persisting | Ensure PHP sessions are enabled and writable |

---

## 🔧 Extending the System

### Add Email Notifications for Overdue
Install PHPMailer via Composer and create `modules/notifications/send_overdue.php`

### Add Barcode Scanning
Replace text inputs with a barcode scanner input — most USB scanners act as keyboard input followed by Enter, which works natively with the current form design.

### Add Role-Based Access
The `users` table already has a `role` column (`admin`/`librarian`).
Use `isAdmin()` helper (already defined in `helpers.php`) to restrict pages.

---

## 📝 License
Free to use for educational and institutional purposes.
