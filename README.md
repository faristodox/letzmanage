# Letz Manage

A single-organization, multi-branch office space booking and management system built with Laravel, Livewire, and Tailwind CSS.

Live: **https://letzmanage.com**

---

## Features

- **Multi-branch support** — manage multiple branches under one organization
- **Office space management** — spaces with photos, types, capacity, and facilities
- **Parent-child space conflicts** — booking a hall automatically blocks its sub-rooms, and vice versa
- **Guest booking** — public 3-step wizard at `/book` (no account required)
- **Staff booking** — internal booking from the admin dashboard
- **Approval workflows** — manual or auto-approval, configurable globally or per branch
- **Role-based access** — Admin, Manager, and Staff roles with scoped permissions
- **Email & Telegram notifications** — on booking submission, approval, and rejection
- **Organization branding** — configurable name and logo shown on the public booking page
- **Calendar view** — visual booking calendar for admins and managers

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13 |
| Frontend | Livewire 3 + Livewire Volt |
| Styling | Tailwind CSS (via Vite) |
| Database | MySQL (production) |
| Auth | Laravel Breeze |
| Notifications | Laravel Mail + Telegram Bot API |
| Hosting | Hostinger Shared Hosting (PHP 8.4) |

---

## Booking Workflow

### Guest

```
Visit /book
  → Choose a space
  → Pick date & time
  → Fill in details (name, email, phone, purpose)
  → Submit
  → Receive confirmation email
  → Admin reviews and approves or rejects
  → Receive approved / rejected email
```

### Admin / Manager

```
Receive notification (email + Telegram)
  → Log in to dashboard
  → Go to Bookings
  → Approve (with optional note) or Reject (with optional reason)
  → Requester is notified automatically
```

---

## Approval Modes

| Mode | Behaviour |
|------|-----------|
| **Manual** | Bookings start as Pending and require admin or manager approval |
| **Auto** | Bookings are immediately approved on creation |

Configurable globally or per branch under **Admin → Settings**.

---

## Space Conflict Rules

- Only **Approved** bookings block time slots — Pending bookings may overlap
- Booking a **parent space** (e.g. Hall) blocks all its **sub-spaces** (e.g. Meeting Room 1 & 2)
- Booking a **sub-space** blocks its **parent only** — sibling sub-spaces remain independently bookable
- Overlap is re-checked at approval time to handle race conditions

---

## Roles & Permissions

| Role | Access |
|------|--------|
| **Admin** | Full access — all branches, users, settings, bookings |
| **Manager** | Manage spaces and bookings for their branch; approve/reject |
| **Staff** | Create bookings for their branch; view own bookings |
| **Guest** | Submit booking requests via the public `/book` page |

---

## Local Development Setup

### Requirements

- PHP 8.4+
- Composer
- Node.js + npm
- MySQL or SQLite

### Steps

```bash
git clone https://github.com/faristodox/letzmanage.git
cd letzmanage

composer install
npm install

cp .env.example .env
php artisan key:generate

# Configure your DB in .env, then:
php artisan migrate --seed
php artisan storage:link

npm run dev
```

Default seeded admin account:
- **Email:** admin@letzmanage.test
- **Password:** password

---

## Production Deployment (Hostinger Shared Hosting)

```bash
ssh -p 65002 u997806794@194.163.35.5

cd ~/letzmanage

# Pull latest changes
git pull

# Run if schema changed
/opt/alt/php84/usr/bin/php artisan migrate --force

# Run if blade files changed
/opt/alt/php84/usr/bin/php artisan view:clear

# Run if .env changed
/opt/alt/php84/usr/bin/php artisan config:clear
```

> Vite assets (`public/build/`) are committed to git since the server has no Node.js.
> Build locally with `npm run build` before pushing.

---

## Key Environment Variables

```env
APP_NAME=Letz Manage
APP_URL=https://letzmanage.com
APP_DEBUG=false

DB_CONNECTION=mysql
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=admin@letzmanage.com
MAIL_FROM_ADDRESS=admin@letzmanage.com

TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

---

## License

Private project. All rights reserved.
