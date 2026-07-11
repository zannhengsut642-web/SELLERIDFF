# Free Fire Likes Generator — Telegram Bot

A PHP Telegram bot with Mini App integration that generates Free Fire likes after watching a rewarded ad via Monetag.

## Features

- Telegram Bot with inline keyboard menu
- Telegram Mini App for ad viewing
- Monetag rewarded interstitial ad integration
- SQLite storage (user tracking, first-user admin)
- Broadcast system for admin messages
- Web-based installer

## Files

| File | Purpose |
|------|---------|
| `install.php` | Web installer — enter bot token + Monetag zone, auto-configures |
| `config.php` | Bot configuration (written by installer) |
| `bot.php` | Webhook handler — all bot logic |
| `db.php` | SQLite database helper |
| `mini_app/index.html` | Telegram Mini App with Monetag ad |
| `set_webhook.php` | Manual webhook registration (fallback) |
| `broadcast.php` | Web-based admin broadcast tool |

## Requirements

- PHP 8.0+ with `pdo_sqlite` extension
- HTTPS-enabled web server
- Telegram Bot Token (from [@BotFather](https://t.me/BotFather))
- Monetag account with zone ID

## Installation

1. Upload all files to your PHP hosting
2. Open `install.php` in your browser
3. Enter your **Bot Token** and **Monetag Zone ID**
4. Click **Install & Set Webhook**
5. Start your bot on Telegram with `/start`

The first user to send `/start` becomes the **admin**.

## Commands

| Command | Access | Action |
|---------|--------|--------|
| `/start` | All users | Show main menu |
| `/broadcast` | Admin only | Send message to all users |

## License

MIT
