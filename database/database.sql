-- Table for users (admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
);

-- Table for events
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    date DATE,
    pair_id INT(11) NULL,
    group_id INT(11) NULL,
    FOREIGN KEY (pair_id) REFERENCES pairs(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Table for photos
CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    description TEXT,
    user_id INT,
    event_id INT(11),
    pair_id INT(11) NULL,
    group_id INT(11) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (pair_id) REFERENCES pairs(id) ON DELETE SET NULL,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL
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

-- Table for group_invitations
CREATE TABLE group_invitations (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    group_id INT(11) NOT NULL,
    inviter_id INT(11) NOT NULL,
    invitee_id INT(11) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_invitation (group_id, invitee_id)
);

-- Table for groups (replaces pairs)
CREATE TABLE groups (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    established_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    start_date DATE NULL,
    created_by INT(11) NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for group members
CREATE TABLE group_members (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    group_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (group_id, user_id)
);

-- Keep the pairs table for backward compatibility
CREATE TABLE pairs (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user1_id INT(11) NOT NULL,
    user2_id INT(11) NOT NULL,
    established_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    start_date DATE NULL,
    group_id INT(11) NULL, -- Reference to the group that replaced this pair
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL,
    UNIQUE KEY unique_pair (user1_id, user2_id)
);
