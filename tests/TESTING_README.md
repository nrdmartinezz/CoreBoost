# CoreBoost Testing Guide

## Test Suite Implementation

This directory contains comprehensive unit tests for the CoreBoost plugin, with a focus on preventing functionality loss during updates.

## Prerequisites

1. **PHP 7.4 or higher** (PHP 8.1+ recommended for AVIF support)
2. **Composer** - PHP dependency manager
3. **GD Library** with AVIF/WebP support

### Installing Composer (Windows)

Download and install from: https://getcomposer.org/download/

Or use Chocolatey:
```powershell
choco install composer
```

## Setup

1. Install PHPUnit and dependencies:
```powershell
cd "d:\natha\Documents\Web Dev Workspace\Code Workspace\CoreBoost\CoreBoost"
composer install
```

2. Verify GD library support:
```powershell
php -r "echo 'GD: ' . (extension_loaded('gd') ? 'YES' : 'NO') . PHP_EOL;"
php -r "echo 'AVIF: ' . (function_exists('imageavif') ? 'YES' : 'NO') . PHP_EOL;"
php -r "echo 'WebP: ' . (function_exists('imagewebp') ? 'YES' : 'NO') . PHP_EOL;"
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
vendor/bin/phpunit tests/unit/ImageFormatOptimizerTest.php
```

### Run with coverage report:
```powershell
composer test-coverage
```

Coverage report will be generated in `tests/coverage/html/index.html`

### Run specific test method:
```powershell
vendor/bin/phpunit --filter test_generate_avif_variant_creates_file_successfully
```

### Verbose output:
```powershell
vendor/bin/phpunit --verbose
```

## Test Structure

### Image Format Optimizer Tests (79 tests)

**Category 1: Variant Generation (7 tests)**
- AVIF/WebP file creation
- Transparency preservation
- Quality settings application
- Corrupted image handling

**Category 2: File Validation (5 tests)**
- JPEG/PNG acceptance
- Unsupported format rejection
- Missing file handling
- Corrupted file detection

**Category 3: GD Library Detection (3 tests)**
- Extension availability
- Function availability (imageavif, imagewebp)

**Category 4: Quality Settings (2 tests)**
- Default quality application
- Constructor option persistence

**Category 5: Error Handling (3 tests)**
- Missing source files
- File overwrite behavior
- Empty/null path handling

## Test Coverage Goals

- **Line Coverage**: 95%+
- **Branch Coverage**: 90%+
- **Method Coverage**: 100%

## Debugging Tests

### Enable verbose error output:
```powershell
vendor/bin/phpunit --debug
```

### Run single test with error details:
```powershell
vendor/bin/phpunit --filter test_name --testdox --colors
```

### Check test execution order:
```powershell
vendor/bin/phpunit --order-by=default --testdox
```

## Known Limitations

1. **AVIF Support**: Requires PHP 8.1+ and GD library compiled with AVIF support
   - Tests will be skipped if not available
   - WebP tests will still run on PHP 7.4+

2. **File System**: Tests create temporary files in system temp directory
   - Cleanup happens automatically in tearDown()
   - Manual cleanup may be needed if tests crash

3. **Performance**: Some tests involve file I/O and image processing
   - May take 5-10 seconds to complete full suite
   - Use `--filter` for faster iteration during development

## Troubleshooting

### "GD library not available"
Install PHP GD extension:
```powershell
# Check current php.ini location
php --ini

# Edit php.ini and uncomment:
extension=gd
```

### "AVIF support not available"
- Upgrade to PHP 8.1+
- Ensure GD was compiled with AVIF support
- Check with: `php -r "var_dump(gd_info());"`

### "WebP support not available"
- Usually available in GD 2.0+
- Check with: `php -r "var_dump(function_exists('imagewebp'));"`

### Permission errors
Ensure write access to:
- System temp directory
- `tests/coverage/` directory

## Continuous Integration

Tests can be integrated into GitHub Actions, GitLab CI, or other CI/CD pipelines:

```yaml
# Example .github/workflows/test.yml
- name: Run PHPUnit tests
  run: composer test
```

## Next Steps

1. **Install dependencies**: `composer install`
2. **Run initial test**: `vendor/bin/phpunit --testdox`
3. **Check coverage**: `composer test-coverage`
4. **Fix any failures**: Review error output and adjust code/tests
5. **Integrate into workflow**: Add to CI/CD pipeline

## Support

For issues with tests or image conversion:
1. Check GD library configuration
2. Verify file permissions
3. Review error logs in WordPress debug mode
4. Run diagnostic: `php -r "var_dump(gd_info());"`
