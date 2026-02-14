Made some drastic changes. Decided to simplify the code.

# lainboard

An omega-simple and lightweight BBS system.
Check out a working example at https://boards.lain.rocks/
---

## Features

- **Anonymous Posting:** Users can post messages without an account.
- **Threaded Discussions:** Organizes conversations into threads.
- **Simple and Lightweight:** Designed for minimal and quick deployment.
- **Moderation Tools:** Basic tools for community management.
- **CAPTCHA:** Implemented to prevent spam and automated posts.

---

## Requirements

- PHP  
- PostgresQL on your server  
- Web server (Apache, Nginx, etc.)  
---

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/tyt4real/lainboard.git
    cd lainboard
    ```

3.  **Database Setup (PostgreSQL):**
    *   Create a PostgreSQL database and user for lainboard.
    *   Update `src/config.php` with your database credentials. The database will be initialized automatically on the first post. See `src/includes/database.php` for more info.

4.  **Web Server Configuration (Apache/Nginx):**
    *   Configure your web server to point its document root to the `src` directory of the cloned repository.

5.  **Configure `config.php`:**
    *   Rename `src/config.example.php` to `src/config.php`.
    *   Edit `src/config.php` to set up database connections, site settings, and other stuff.

6.  **Configure `database.php`:**
    *   Rename `src/includes/datbase.example.php` to `src/includes/database.php`.
    *   Edit `src/includes/database.php`to ACTUALLY set up the database connection.

7.  **Access your board:**
    *   Open your web browser and navigate to your server's address.
    *   Make your first post to automatically initialize the database. Add an administrator with the included script.
 

---

## Usage

- **Posting:** Navigate to any board and use the posting form at the top to create a new thread or reply to an existing one. Duh.
- **Moderation:** Access the moderation panel to manage posts, threads, and users.

---

## Roadmap
 - Post bumping - [X]
 - Working replies - [X]
 - Thread replies - [X]
 - A nice homepage - [X]
 - Redo the configuration system to be interactive - [X]
 - Moderator/Administrator capcodes - [X]
 - Tripcodes - [X]
 - OpenPGP poster verification - [X] 
 - Administration/Moderation panel - [X]
 - Banners - []
 - CSRF verification - [X]
 - Setup a working example - [X]
 - Overboard - [X]
 - Add a posting CAPTCHA - [X]
 - Post constraints per board - []
 - Board rules customizing in admin panel - [X]
 - Public admin log - []
 - Clientside settings - [X]
 - Administration notes shared among admins and mods - []
 - Categorizing content as NSFW / SFW - []
 - BBCode per request - []

---

## Special thanks

- Special thanks to ChurchOfTuring for letting me steal his theme management idea
- Special thanks to whoever made the yotsuba theme :)