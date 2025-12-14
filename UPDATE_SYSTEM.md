# CoreBoost Update System

## Overview

CoreBoost uses an automated GitHub-based update system that allows WordPress sites to receive plugin updates directly from GitHub releases. This prevents site breakage during manual updates by implementing version migration routines and safe upgrade handling.

## Components

### 1. GitHub Update Checker

**Location**: `coreboost.php` (lines 57-67)

Uses the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library to:
- Poll GitHub releases every 12 hours
- Compare installed version with latest release
- Display update notices in WordPress admin
- Enable one-click updates from the WordPress dashboard

### 2. Version Tracking

**Location**: `includes/class-activator.php`

Stores the installed version in `coreboost_version` option to detect upgrades.

### 3. Migration System

**Location**: `includes/core/class-migration.php`

Handles version-specific upgrades with automatic option migration and cache clearing.

### 4. Backup System

Before running migrations, current options are backed up to `coreboost_options_backup_{version}`.

### 5. Uninstall Cleanup

**Location**: `uninstall.php`

Removes all plugin data when deleted via WordPress admin.

## How to Create a Release

1. Update version in `coreboost.php`
2. Update `CHANGELOG.md`
3. Commit changes
4. Create and push tag:
   ```bash
   git tag -a v3.0.1 -m "Release v3.0.1"
   git push origin v3.0.1
   ```
5. GitHub Actions automatically creates release with ZIP

## Sites will receive the update automatically within 12 hours.

## Private Repository Setup

If your CoreBoost repository is private, you need to configure GitHub authentication to enable automatic updates.

### Option 1: Environment Variable (Recommended for CI/CD)

Set the `GITHUB_TOKEN` environment variable on your server:

```bash
export GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Or in `.htaccess` (if using Apache):

```apache
SetEnv GITHUB_TOKEN ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Option 2: WordPress Configuration (Recommended for Managed Hosting)

Add to your `wp-config.php`:

```php
define('COREBOOST_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxx');
```

### Creating a GitHub Personal Access Token

1. Go to https://github.com/settings/tokens
2. Click "Generate new token (classic)"
3. Select scopes:
   - `repo` (full control of private repositories)
   - `read:user` (read user profile data)
4. Generate and copy the token
5. Store securely (never commit to version control)

### Verification

After setting up authentication:

1. Check WordPress debug log: `wp-content/debug.log`
2. Look for messages like: "CoreBoost: Update checker initialized successfully"
3. Go to WordPress Admin → Plugins and check for update notifications
4. Wait up to 12 hours for the update check transient to expire

### Troubleshooting Private Repo Updates

**Error: "Could not determine if updates are available"**
- Check GitHub token is valid: https://api.github.com/user (should return user info)
- Verify token has `repo` scope
- Check token is not expired
- Check `wp-content/debug.log` for specific error messages

**Error: "Base URL: "/repos/:user/:repo/releases/latest", HTTP status code: 404"**
- Repository URL may be incorrect
- Token may not have proper permissions
- Repository may not have any releases yet

**Fix: Make Repository Public (Alternative)**

For simpler setup without authentication:

1. Go to GitHub repository settings
2. Scroll to "Danger zone"
3. Click "Make this repository public"
4. Update checks will work immediately without authentication

---
