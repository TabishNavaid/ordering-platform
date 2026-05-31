# The Local Provisions - Ordering Platform

A custom, full-stack ordering platform built for small businesses. Features a warm, human-crafted design and zero heavy framework dependencies (plain PHP and Vanilla JS).

## Features
- **Customer Portal:** Browse products, manage cart, place orders, view order history.
- **Admin Dashboard:** View revenue statistics, manage incoming orders, print receipts, and manage product inventory.
- **Security:** Secure password hashing, CSRF protection on all forms, and PDO prepared statements to prevent SQL injection.
- **Design:** Custom responsive CSS grid, beautiful typography (Fraunces + Inter), and a warm, earthy color palette.

## Prerequisites
- PHP 8.0 or higher
- MySQL / MariaDB
- A local server environment (like XAMPP, MAMP, or Laravel Valet/Herd)

## Setup Instructions

1. **Database Setup:**
   - Create a new MySQL database named `tau_ordering` (or your preferred name).
   - Import the `database.sql` file provided in the root directory. This will create the schema and insert some seed data.
   
   ```bash
   mysql -u root -p tau_ordering < database.sql
   ```

2. **Configuration:**
   - Open `includes/db.php`.
   - Update the database connection credentials (`$user`, `$pass`, `$db` if you changed the name).

3. **Folder Permissions:**
   - Ensure the `uploads/` directory is writable by your web server, as this is where product images will be stored.
   
   ```bash
   chmod -R 775 uploads/
   ```

4. **Web Server Setup:**
   - Point your local web server's document root to the `public/` directory inside this project.
   - For a quick test using PHP's built-in server, run the following from the `public/` directory:
   
   ```bash
   cd public
   php -S localhost:8000
   ```
   - Then visit `http://localhost:8000` in your browser.

## Default Logins

**Admin Account**
- **URL:** `/admin/login.php`
- **Email:** `admin@example.com`

> [!WARNING]
> The default admin password is set in `database.sql` (`AdminSecure2026!`). You **must** change this immediately in a production environment or create a new admin account and delete the default one.

## Folder Structure

- `public/`: The web root. Contains all accessible PHP files and static assets.
  - `admin/`: The admin dashboard and management tools.
  - `assets/`: CSS, JS, and image files.
- `includes/`: Core logic files, database connections, and shared UI templates (header/footer). This should ideally be placed outside the web root in a production environment.
- `uploads/`: Uploaded product images.

## Design Notes
- The primary stylesheet is located at `public/assets/css/style.css`.
- Colors and typography are managed via CSS variables (`:root`) at the top of the stylesheet. Change these variables to easily re-theme the entire application.
