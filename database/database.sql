-- Drop tables if they exist to allow for easy re-creation
DROP TABLE IF EXISTS photos;
DROP TABLE IF EXISTS pairs;
DROP TABLE IF EXISTS pair_requests;
DROP TABLE IF EXISTS users;

-- Table for users (admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
);

-- Table for photos
CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    description TEXT,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table for pair_requests
CREATE TABLE pair_requests (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    requester_id INT(11) NOT NULL,
    recipient_id INT(11) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_request (requester_id, recipient_id)
);

-- Table for pairs
CREATE TABLE pairs (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user1_id INT(11) NOT NULL,
    user2_id INT(11) NOT NULL,
    established_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pair (user1_id, user2_id)
);

-- Add pair_id column to photos table
ALTER TABLE photos
    ADD COLUMN pair_id INT(11) NULL,
    ADD CONSTRAINT fk_photos_pair_id
    FOREIGN KEY (pair_id) REFERENCES pairs(id) ON DELETE SET NULL;

-- Add date column to photos table
ALTER TABLE photos ADD COLUMN date DATE NULL AFTER description;