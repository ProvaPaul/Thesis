CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'annotator') NOT NULL DEFAULT 'annotator',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dataset (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source_row_hash CHAR(64) NOT NULL UNIQUE,
  word_id VARCHAR(50) NULL,
  target_word VARCHAR(120) NOT NULL,
  sense_id VARCHAR(50) NULL,
  sense_gloss VARCHAR(255) NOT NULL,
  text TEXT NOT NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dataset_target_word (target_word),
  INDEX idx_dataset_sense_gloss (sense_gloss),
  FULLTEXT INDEX ft_dataset_search (target_word, sense_gloss, text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annotations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sentence_id INT NOT NULL,
  annotator_id INT NOT NULL,
  selected_sense VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_annotation_once (sentence_id, annotator_id),
  CONSTRAINT fk_annotations_dataset FOREIGN KEY (sentence_id) REFERENCES dataset(id) ON DELETE CASCADE,
  CONSTRAINT fk_annotations_user FOREIGN KEY (annotator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, password, role)
VALUES
('Admin', 'admin@example.com', '$2y$10$Ljg3J3OYKtSZ47cdJGTgmOXFpg.J.DhSt.dS3F2KSoFkY0XsKYeOW', 'admin'),
('Annotator One', 'annotator@example.com', '$2y$10$Ljg3J3OYKtSZ47cdJGTgmOXFpg.J.DhSt.dS3F2KSoFkY0XsKYeOW', 'annotator')
ON DUPLICATE KEY UPDATE email = VALUES(email);
