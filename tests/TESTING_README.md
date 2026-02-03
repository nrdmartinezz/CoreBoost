# CoreBoost Testing Guide

## Test Suite Implementation

This directory contains tests for the CoreBoost plugin, with a focus on ensuring core functionality works correctly.

## Prerequisites

1. **PHP 7.4 or higher**
2. **Composer** - PHP dependency manager

### Installing Composer (Windows)

Download and install from: https://getcomposer.org/download/

Or use Chocolatey:
```powershell
choco install composer
```

## Setup

1. Install PHPUnit and dependencies:
```powershell
cd "d:\natha\Documents\Web Dev Workspace\Code Workspace\CoreBoost"
composer install
```

## Running Tests

### Run all tests:
```powershell
composer test
```

Or directly:
```powershell
vendor/bin/phpunit
```

### Run specific test suite:
```powershell
vendor/bin/phpunit tests/integration/ContextHelperTest.php
```

### Run with coverage report:
```powershell
composer test-coverage
```

Coverage report will be generated in `tests/coverage/html/index.html`

### Verbose output:
```powershell
vendor/bin/phpunit --verbose
```

## Test Structure

### Context Helper Tests (`tests/integration/ContextHelperTest.php`)

Tests the Context_Helper utility class which handles:
- Elementor preview detection
- Optimization skip logic
- Debug logging

### Test Mocks (`tests/mocks/`)

Contains mock classes used to simulate WordPress and plugin dependencies during testing:
- `class-context-helper-mock.php` - Mock for Context_Helper with test control methods

## GitHub Actions CI

Tests are automatically run on push/PR to main and develop branches via `.github/workflows/test.yml`:

- **Syntax Check**: PHP 7.4, 8.0, 8.1, 8.2
- **PHPUnit Tests**: PHP 7.4, 8.1
- **WordPress Compatibility**: WP 6.1-6.4 with PHP 7.4/8.1
- **Code Quality**: PHPCS with WordPress standards
- **Plugin Activation**: Minimal WordPress environment testing
- **Build Test**: ZIP creation verification

## Test Coverage Goals

- **Line Coverage**: 80%+
- **Method Coverage**: 90%+

## Debugging Tests

### Enable verbose error output:
```powershell
vendor/bin/phpunit --debug
```

### Run single test with error details:
```powershell
vendor/bin/phpunit --filter test_name --testdox --colors
```

## Troubleshooting

### Permission errors
Ensure write access to:
- System temp directory
- `tests/coverage/` directory

### Test failures after updates
1. Run `composer install` to ensure dependencies are up to date
2. Check for any new mock requirements in `tests/bootstrap.php`
3. Review error messages for missing functions

## Support

For issues with tests:
1. Check PHP version compatibility
2. Verify Composer dependencies are installed
3. Review error logs
4. Check if mock classes need updates
