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
