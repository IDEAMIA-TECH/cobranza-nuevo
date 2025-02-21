CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    attempts INT DEFAULT 0,
    last_attempt DATETIME,
    UNIQUE KEY unique_email (email)
); 