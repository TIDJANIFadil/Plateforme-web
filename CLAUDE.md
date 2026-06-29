# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

IFRI Portail is a PHP/MySQL web application for managing administrative documents at Institut de Formation et de Recherche en Informatique (IFRI). It has two portals: a student dashboard and an admin dashboard.

## Tech Stack

- **Backend**: PHP 8+ (vanilla, no framework), MySQL via PDO
- **Frontend**: Tailwind CSS (CDN), vanilla JavaScript, Material Symbols
- **Database**: MySQL (`ifri_gestion_docs`) with XAMPP
- **Email**: PHPMailer (in admin space)

## Project Structure

```
/
├── index.php                  # Login page
├── auth_process.php           # Login/signup POST handler
├── ifri_gestion_docs.php      # PDO connection (root@localhost, no password)
├── dashboard.php              # Student main dashboard
├── profile.php                # Student profile & password change
├── settings.php               # Student settings
├── notifications.php          # Student notifications
├── mes_demandes.php           # Student requests
├── add_request.php            # Submit new request
├── logout.php                 # Session destroy
├── migration_tables.sql       # DB schema (admin table, notifications)
├── espace_administrateur/     # Admin portal
│   ├── dashboard_admin.php
│   ├── profile_admin.php
│   ├── settings_admin.php
│   ├── notifications_admin.php
│   ├── mes_demandes_admin.php
│   ├── traiter_demande.php
│   ├── action_demande.php
│   ├── process_inscription.php
│   └── PHPMailer/             # Email library
├── uploads/                   # User uploads
├── images/                    # Static images (e.g., IFRI.png)
└── sessions/                  # PHP session files
```

## Key Architecture Notes

- **No framework**: Raw PHP with `require_once` for includes
- **Session-based auth**: `$_SESSION['user_id']` for students, `$_SESSION['admin_logged_in']` for admins
- **Password hashing**: bcrypt via `password_hash()` / `password_verify()`
- **Default admin**: `admin@ifri.bj` / `admin123` (hardcoded hash in `auth_process.php`)
- **DB config**: `ifri_gestion_docs.php` contains hardcoded credentials for XAMPP default (root, no password)

## Running Locally

1. Start Apache and MySQL via XAMPP Control Panel
2. Create the database: `mysql -u root < migration_tables.sql` (ensure `ifri_gestion_docs` exists first)
3. Access at `http://localhost/Plateforme-web/`

## Common MySQL Commands

```bash
# Import schema
mysql -u root ifri_gestion_docs < migration_tables.sql

# Dump database
mysqldump -u root ifri_gestion_docs > backup.sql
```

## Session Data

Student sessions include: `user_id`, `nom`, `prenom`, `role` (`etudiant`), `user_matricule`, `user_name`, `login_time`

Admin sessions include: `admin_logged_in`, `admin_id`, `admin_email`, `role` (`admin`), `login_time`

## Claude Code Plugins Setup

The project is configured to use 6 Claude Code plugins/skills. Installation is partially complete:

### Directory structure created at `.claude/`
- `settings.json` with all plugin registrations
- `plugins/` directory for cloned plugin repos
- `skills/` directory for skill assets

### Plugin Sources
| Plugin | Source | Type |
|--------|--------|------|
| Superpowers | github.com/obra/superpowers | GitHub (needs clone) |
| Frontend Design | Official Anthropic | Register in settings |
| Code Review | Official Anthropic | Register in settings |
| Security Guidance | Official Anthropic | Register in settings |
| Claude-Mem | github.com/thedotmack/claude-mem | GitHub (needs clone) |
| GStack | github.com/garrytan/gstack | GitHub (needs clone) |

### To complete installation
Run: `bash setup_plugins.sh` or `.\install_all.bat` (Windows)
Or manually: `claude plugin install frontend-design code-review security-guidance`
And: `git clone --depth 1 https://github.com/obra/superpowers.git .claude/plugins/superpowers`
And: `git clone --depth 1 https://github.com/thedotmack/claude-mem.git .claude/plugins/claude-mem`
And: `git clone --depth 1 https://github.com/garrytan/gstack.git .claude/plugins/gstack`
