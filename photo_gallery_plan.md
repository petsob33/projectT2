# Photo Gallery Application Plan

This document outlines the plan for building a simple web application for a Tinder-style photo gallery for couples, using pure PHP, HTML, CSS, and JavaScript, with a MySQL database.

## 1. Project Setup and Structure

The project will follow the specified directory structure:

```
/ (root)
├── index.php
├── login.php
├── logout.php
├── admin/
│   ├── dashboard.php
│   ├── upload.php
│   └── delete.php
├── includes/
│   ├── db.php
│   └── auth.php
├── uploads/
├── assets/
│   ├── style.css
│   └── script.js
```

The `uploads/` directory will require write permissions for the web server.

## 2. Database Design and Setup

A MySQL database will be used with the following tables:

```sql
-- Table for users (admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
);

-- Table for photos
CREATE TABLE photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    description TEXT,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

*   `users`: Stores admin user credentials. `password_hash` will store the hashed password.
*   `photos`: Stores information about each photo, including the filename, a description, and the ID of the user who uploaded it.

## 3. Core Functionality

*   **Database Connection (`includes/db.php`):**
    *   Establish a connection to the MySQL database using PDO or mysqli.
    *   Include this file in any script that needs database access.
*   **Authentication (`includes/auth.php`, `login.php`, `logout.php`):**
    *   `includes/auth.php`: Contains functions for starting/managing sessions, checking if a user is logged in, and handling login/logout logic.
    *   `login.php`: Displays a login form. Processes submitted credentials, verifies the username and hashed password against the `users` table, and starts a session upon successful login. Redirects to `index.php` or `admin/dashboard.php`.
    *   `logout.php`: Destroys the user's session and redirects to `login.php`.
*   **User Interface (`index.php`, `assets/style.css`, `assets/script.js`):**
    *   `index.php`:
        *   Includes `includes/db.php` and `includes/auth.php`.
        *   Fetches photo data from the `photos` table.
        *   Displays one photo at a time within a container element.
        *   Includes a "Next" button.
        *   Links to `assets/style.css` and `assets/script.js`.
    *   `assets/style.css`:
        *   Styles the layout, photo container, and the photo itself.
    *   `assets/script.js`:
        *   Handles the click event for the "Next" button to load the next photo.
        *   Communicates with the server (potentially via AJAX, though keeping it pure PHP might mean page reloads or fetching next photo data directly in the script on page load and cycling through a pre-loaded list) to get the next photo data. Given the "pure PHP" constraint, fetching all photo data on page load and cycling through it in JS might be simpler than AJAX.
*   **Admin Interface (`admin/dashboard.php`, `admin/upload.php`, `admin/delete.php`):**
    *   Each admin page will include `includes/auth.php` and check if the user is logged in and has admin privileges (a simple check based on session data will suffice for this basic app).
    *   `admin/dashboard.php`:
        *   Displays a list of all photos from the `photos` table, showing the image and description.
        *   Provides links or forms to trigger delete actions for each photo.
        *   Includes a link to the `admin/upload.php` page.
    *   `admin/upload.php`:
        *   Displays a form with a file input for selecting an image and a text input for the description.
        *   Processes the form submission:
            *   Validates the uploaded file (type, size).
            *   Moves the uploaded file to the `uploads/` directory with a unique filename.
            *   Inserts the filename, description, and the logged-in user's ID into the `photos` table.
            *   Redirects back to `admin/dashboard.php`.
    *   `admin/delete.php`:
        *   Receives the photo ID to be deleted (e.g., via GET or POST).
        *   Fetches the filename from the database based on the ID.
        *   Deletes the corresponding file from the `uploads/` directory.
        *   Deletes the record from the `photos` table.
        *   Redirects back to `admin/dashboard.php`.

## 4. Basic Security Considerations

*   Use `password_hash()` and `password_verify()` for storing and checking user passwords.
*   Prevent direct access to files in the `includes/` directory (e.g., using `.htaccess` if on Apache).
*   Sanitize user inputs to prevent SQL injection and XSS attacks (though for this basic app, focusing on core functionality first is reasonable).