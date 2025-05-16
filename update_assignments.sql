USE php_bie_daalt;

ALTER TABLE assignments 
ADD COLUMN file_path VARCHAR(255) AFTER due_date,
ADD COLUMN max_score INT DEFAULT 100 AFTER file_path,
ADD COLUMN allow_late TINYINT(1) DEFAULT 1 AFTER max_score,
ADD COLUMN created_by INT NOT NULL AFTER allow_late,
ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE; 