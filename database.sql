-- ============================================================
--  Library Management System — Database Setup Script
-- ============================================================
CREATE DATABASE IF NOT EXISTS library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(80)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    full_name  VARCHAR(150) NOT NULL,
    role       ENUM('admin','librarian') DEFAULT 'librarian',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default login:  admin / Library@2024
INSERT INTO users (username, password, full_name, role) VALUES
('admin','','System Administrator','admin');

CREATE TABLE IF NOT EXISTS books (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id          VARCHAR(50)  NOT NULL UNIQUE,
    title            VARCHAR(255) NOT NULL,
    author           VARCHAR(150) NOT NULL,
    category         VARCHAR(100) NOT NULL,
    shelf_location   VARCHAR(60)  DEFAULT NULL,
    status           ENUM('Available','Borrowed','Missing') DEFAULT 'Available',
    condition_status ENUM('Good','Damaged') DEFAULT 'Good',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status(status), INDEX idx_category(category),
    FULLTEXT idx_search(title, author)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS borrowers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    borrower_id VARCHAR(50)  NOT NULL UNIQUE,
    full_name   VARCHAR(150) NOT NULL,
    category    ENUM('Student','Teacher','Staff') NOT NULL,
    department  VARCHAR(100) DEFAULT NULL,
    phone       VARCHAR(30)  DEFAULT NULL,
    email       VARCHAR(150) DEFAULT NULL,
    status      ENUM('Active','Inactive') DEFAULT 'Active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category(category), INDEX idx_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS borrow_records (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_id   VARCHAR(50) NOT NULL UNIQUE,
    book_id     INT UNSIGNED NOT NULL,
    borrower_id INT UNSIGNED NOT NULL,
    borrow_date DATE NOT NULL,
    due_date    DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status      ENUM('Returned','Not Returned') DEFAULT 'Not Returned',
    notes       TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id)     REFERENCES books(id)     ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (borrower_id) REFERENCES borrowers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_status(status), INDEX idx_borrow_date(borrow_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED DEFAULT NULL,
    action     VARCHAR(100) NOT NULL,
    target     VARCHAR(100) DEFAULT NULL,
    target_id  INT UNSIGNED DEFAULT NULL,
    detail     TEXT DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Sample Books
INSERT INTO books (book_id, title, author, category, shelf_location, status, condition_status) VALUES
('BK-001','Introduction to Algorithms','Thomas H. Cormen','Computer Science','A-1','Available','Good'),
('BK-002','Clean Code','Robert C. Martin','Computer Science','A-2','Available','Good'),
('BK-003','The Great Gatsby','F. Scott Fitzgerald','Fiction','B-1','Borrowed','Good'),
('BK-004','To Kill a Mockingbird','Harper Lee','Fiction','B-2','Available','Good'),
('BK-005','A Brief History of Time','Stephen Hawking','Science','C-1','Available','Good'),
('BK-006','The Pragmatic Programmer','David Thomas','Computer Science','A-3','Available','Damaged'),
('BK-007','Calculus: Early Transcendentals','James Stewart','Mathematics','D-1','Available','Good'),
('BK-008','Organic Chemistry','Paula Bruice','Chemistry','E-1','Missing','Good'),
('BK-009','Principles of Economics','N. Gregory Mankiw','Economics','F-1','Available','Good'),
('BK-010','The Art of War','Sun Tzu','History','G-1','Available','Good'),
('BK-011','Design Patterns','Gang of Four','Computer Science','A-4','Available','Good'),
('BK-012','Sapiens','Yuval Noah Harari','History','G-2','Borrowed','Good'),
('BK-013','Thinking Fast and Slow','Daniel Kahneman','Psychology','H-1','Available','Good'),
('BK-014','Atomic Habits','James Clear','Self-Help','H-2','Available','Good'),
('BK-015','The Alchemist','Paulo Coelho','Fiction','B-3','Available','Good');

-- Sample Borrowers
INSERT INTO borrowers (borrower_id, full_name, category, department, phone, email) VALUES
('STU-001','Alice Wanjiru Kamau','Student','Computer Science','0712345678','alice@school.ac.ke'),
('STU-002','Brian Otieno Odhiambo','Student','Mathematics','0723456789','brian@school.ac.ke'),
('TCH-001','Dr. Mary Njeri Mwangi','Teacher','Computer Science','0734567890','mary@school.ac.ke'),
('TCH-002','Prof. James Kariuki','Teacher','Physics','0745678901','james@school.ac.ke'),
('STF-001','Peter Maina Njoroge','Staff','Administration','0756789012','peter@school.ac.ke'),
('STU-003','Grace Achieng Ooko','Student','Biology','0767890123','grace@school.ac.ke'),
('STU-004','David Kiprop Cheruiyot','Student','Economics','0778901234','david@school.ac.ke'),
('TCH-003','Ms. Faith Wambui Ndungu','Teacher','English','0789012345','faith@school.ac.ke');

-- Sample Records
INSERT INTO borrow_records (record_id,book_id,borrower_id,borrow_date,due_date,return_date,status) VALUES
('REC-001',3,1,DATE_SUB(CURDATE(),INTERVAL 5 DAY),DATE_ADD(DATE_SUB(CURDATE(),INTERVAL 5 DAY),INTERVAL 14 DAY),NULL,'Not Returned'),
('REC-002',12,3,DATE_SUB(CURDATE(),INTERVAL 20 DAY),DATE_ADD(DATE_SUB(CURDATE(),INTERVAL 20 DAY),INTERVAL 14 DAY),NULL,'Not Returned'),
('REC-003',1,2,DATE_SUB(CURDATE(),INTERVAL 30 DAY),DATE_ADD(DATE_SUB(CURDATE(),INTERVAL 30 DAY),INTERVAL 14 DAY),DATE_SUB(CURDATE(),INTERVAL 16 DAY),'Returned'),
('REC-004',5,4,DATE_SUB(CURDATE(),INTERVAL 10 DAY),DATE_ADD(DATE_SUB(CURDATE(),INTERVAL 10 DAY),INTERVAL 14 DAY),DATE_SUB(CURDATE(),INTERVAL 2 DAY),'Returned');
