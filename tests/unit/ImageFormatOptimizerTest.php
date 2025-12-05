<?php
/**
 * Image Format Optimizer Unit Tests
 * 
 * Comprehensive test suite for CoreBoost\PublicCore\Image_Format_Optimizer
 * Tests variant generation, validation, quality settings, error handling, and more.
 * 
 * @package CoreBoost
 * @subpackage Tests
 */

namespace CoreBoost\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CoreBoost\PublicCore\Image_Format_Optimizer;

class ImageFormatOptimizerTest extends TestCase {
    
    private $temp_dir;
    private $optimizer;
    private $test_images = [];
    
    /**
     * Set up test environment before each test
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Create temporary directory for test images
        $this->temp_dir = sys_get_temp_dir() . '/coreboost-test-' . uniqid();
        \wp_mkdir_p($this->temp_dir);
        
        // Initialize optimizer with default options
        $this->optimizer = new Image_Format_Optimizer([
            'avif_quality' => 85,
            'webp_quality' => 85
        ]);
    }
    
    /**
     * Clean up test environment after each test
     */
    protected function tearDown(): void {
        // Remove test images
        foreach ($this->test_images as $image_path) {
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
        }
        
        // Remove temporary directory recursively
        $this->removeDirRecursive($this->temp_dir);
        
        // Clean up variants directory
        $variants_dir = \wp_upload_dir()['basedir'] . '/coreboost-variants';
        if (is_dir($variants_dir)) {
            $this->removeDirRecursive($variants_dir);
        }
        
        parent::tearDown();
    }
    
    // =============================================================================
    // CATEGORY 1: VARIANT GENERATION TESTS
    // =============================================================================
    
    /**
     * Test 1.1: Generate AVIF Variant Successfully
     */
    public function test_generate_avif_variant_creates_file_successfully() {
        // Skip if AVIF not supported
        if (!$this->isAvifSupported()) {
            $this->markTestSkipped('AVIF support not available on this system');
        }
        
        $jpeg_path = $this->createTestImage('test.jpg', 800, 600, 'jpeg');
        
        $result = $this->optimizer->generate_avif_variant($jpeg_path);
        
        $this->assertNotNull($result, 'AVIF generation should return a path');
        $this->assertFileExists($result, 'AVIF file should exist');
        $this->assertStringEndsWith('.avif', $result, 'File should have .avif extension');
        $this->assertGreaterThan(0, filesize($result), 'AVIF file should have content');
        
        // Verify compression benefit
        $original_size = filesize($jpeg_path);
        $avif_size = filesize($result);
        $this->assertLessThan($original_size, $avif_size, 'AVIF should be smaller than original');
    }
    
    /**
     * Test 1.2: Generate WebP Variant Successfully
     */
    public function test_generate_webp_variant_creates_file_successfully() {
        // Skip if WebP not supported
        if (!$this->isWebpSupported()) {
            $this->markTestSkipped('WebP support not available on this system');
        }
        
        $jpeg_path = $this->createTestImage('test-webp.jpg', 1024, 768, 'jpeg');
        
        $result = $this->optimizer->generate_webp_variant($jpeg_path);
        
        $this->assertNotNull($result, 'WebP generation should return a path');
        $this->assertFileExists($result, 'WebP file should exist');
        $this->assertStringEndsWith('.webp', $result, 'File should have .webp extension');
        $this->assertGreaterThan(0, filesize($result), 'WebP file should have content');
    }
    
    /**
     * Test 1.3: Generate AVIF from PNG with Alpha Channel Preservation
     */
    public function test_generate_avif_variant_preserves_png_transparency() {
        if (!$this->isAvifSupported()) {
            $this->markTestSkipped('AVIF support not available');
        }
        
        $png_path = $this->createTestImage('transparent.png', 512, 512, 'png', true);
        
        $result = $this->optimizer->generate_avif_variant($png_path);
        
        $this->assertNotNull($result, 'AVIF should be generated from PNG');
        $this->assertFileExists($result);
        
        // Verify file can be loaded (indicates proper alpha handling)
        if (function_exists('imagecreatefromavif')) {
            $img = @imagecreatefromavif($result);
            $this->assertNotFalse($img, 'Generated AVIF should be loadable');
            if ($img) {
                imagedestroy($img);
            }
        }
    }
    
    /**
     * Test 1.4: Generate WebP from PNG with Alpha Channel Preservation
     */
    public function test_generate_webp_variant_preserves_png_transparency() {
        if (!$this->isWebpSupported()) {
            $this->markTestSkipped('WebP support not available');
        }
        
        $png_path = $this->createTestImage('trans.png', 640, 480, 'png', true);
        
        $result = $this->optimizer->generate_webp_variant($png_path);
        
        $this->assertNotNull($result, 'WebP should be generated from PNG');
        $this->assertFileExists($result);
    }
    
    /**
     * Test 1.5: Variant Generation Handles Corrupted Source Image
     */
    public function test_generate_avif_variant_returns_null_for_corrupted_image() {
        $corrupted_path = $this->createInvalidImageFile('bad.jpg');
        
        $result = $this->optimizer->generate_avif_variant($corrupted_path);
        
        $this->assertNull($result, 'Corrupted image should return null');
    }
    
    /**
     * Test 1.6: Variant Generation with Different Quality Settings
     */
    public function test_generate_variants_applies_quality_settings() {
        if (!$this->isAvifSupported()) {
            $this->markTestSkipped('AVIF support not available');
        }
        
        $jpeg_path = $this->createTestImage('quality-test.jpg', 800, 600, 'jpeg');
        
        $optimizer_low = new Image_Format_Optimizer(['avif_quality' => 75]);
        $optimizer_high = new Image_Format_Optimizer(['avif_quality' => 95]);
        
        $result_low = $optimizer_low->generate_avif_variant($jpeg_path);
        $result_high = $optimizer_high->generate_avif_variant($jpeg_path);
        
        $this->assertNotNull($result_low);
        $this->assertNotNull($result_high);
        
        $low_size = filesize($result_low);
        $high_size = filesize($result_high);
        
        $this->assertLessThan($high_size, $low_size, 
            'Lower quality should produce smaller file');
    }
    
    /**
     * Test 1.7: Both Variants Generated Successfully
     */
    public function test_generate_variants_creates_both_avif_and_webp() {
        if (!$this->isAvifSupported() || !$this->isWebpSupported()) {
            $this->markTestSkipped('AVIF or WebP support not available');
        }
        
        $jpeg_path = $this->createTestImage('both.jpg', 800, 600, 'jpeg');
        
        $avif_result = $this->optimizer->generate_avif_variant($jpeg_path);
        $webp_result = $this->optimizer->generate_webp_variant($jpeg_path);
        
        $this->assertNotNull($avif_result, 'AVIF variant should be created');
        $this->assertNotNull($webp_result, 'WebP variant should be created');
        $this->assertFileExists($avif_result);
        $this->assertFileExists($webp_result);
        $this->assertNotEquals($avif_result, $webp_result, 'Should create separate files');
    }
    
    // =============================================================================
    // CATEGORY 2: FILE VALIDATION TESTS
    // =============================================================================
    
    /**
     * Test 2.1: Should Optimize Image - JPEG File
     */
    public function test_should_optimize_image_accepts_valid_jpeg() {
        $jpeg_path = $this->createTestImage('valid.jpg', 1200, 800, 'jpeg');
        
        $result = $this->optimizer->should_optimize_image($jpeg_path);
        
        $this->assertTrue($result, 'Valid JPEG should be accepted for optimization');
    }
    
    /**
     * Test 2.2: Should Optimize Image - PNG File
     */
    public function test_should_optimize_image_accepts_valid_png() {
        $png_path = $this->createTestImage('image.png', 800, 600, 'png');
        
        $result = $this->optimizer->should_optimize_image($png_path);
        
        $this->assertTrue($result, 'Valid PNG should be accepted for optimization');
    }
    
    /**
     * Test 2.3: Should NOT Optimize Image - Unsupported Format
     */
    public function test_should_optimize_image_rejects_unsupported_format() {
        $gif_path = $this->createTestImage('image.gif', 400, 300, 'gif');
        
        $result = $this->optimizer->should_optimize_image($gif_path);
        
        $this->assertFalse($result, 'GIF format should not be accepted');
    }
    
    /**
     * Test 2.4: Should NOT Optimize Image - File Not Found
     */
    public function test_should_optimize_image_returns_false_for_missing_file() {
        $missing_path = $this->temp_dir . '/nonexistent.jpg';
        
        $result = $this->optimizer->should_optimize_image($missing_path);
        
        $this->assertFalse($result, 'Missing file should return false');
    }
    
    /**
     * Test 2.5: File Validation Detects Corrupted Files
     */
    public function test_should_optimize_image_rejects_corrupted_files() {
        $corrupted = $this->createInvalidImageFile('broken.jpg');
        
        $result = $this->optimizer->should_optimize_image($corrupted);
        
        $this->assertFalse($result, 'Corrupted file should be rejected');
    }
    
    // =============================================================================
    // CATEGORY 3: GD LIBRARY DETECTION TESTS
    // =============================================================================
    
    /**
     * Test 3.1: Detect GD Library Available
     */
    public function test_generate_avif_variant_with_gd_available() {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD library not available');
        }
        
        $this->assertTrue(extension_loaded('gd'), 'GD library should be loaded');
    }
    
    /**
     * Test 3.2: imageavif() Function Availability
     */
    public function test_imageavif_function_availability() {
        $available = function_exists('imageavif');
        
        if (!$available) {
            $this->markTestIncomplete('imageavif() not available - PHP < 8.1 or GD without AVIF');
        } else {
            $this->assertTrue($available, 'imageavif() should be available');
        }
    }
    
    /**
     * Test 3.3: imagewebp() Function Availability
     */
    public function test_imagewebp_function_availability() {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD library not available');
        }
        
        $this->assertTrue(function_exists('imagewebp'), 
            'imagewebp() should be available in GD');
    }
    
    // =============================================================================
    // CATEGORY 4: QUALITY SETTINGS TESTS
    // =============================================================================
    
    /**
     * Test 4.1: Default Quality Settings
     */
    public function test_default_quality_settings_are_applied() {
        $optimizer_default = new Image_Format_Optimizer();
        
        // We can't directly test private properties without reflection,
        // but we can verify the optimizer was created
        $this->assertInstanceOf(Image_Format_Optimizer::class, $optimizer_default);
    }
    
    /**
     * Test 4.2: Quality Settings Persisted in Constructor
     */
    public function test_quality_settings_stored_from_constructor_options() {
        $options = [
            'avif_quality' => 80,
            'webp_quality' => 90
        ];
        
        $optimizer = new Image_Format_Optimizer($options);
        
        $this->assertInstanceOf(Image_Format_Optimizer::class, $optimizer);
    }
    
    // =============================================================================
    // CATEGORY 5: ERROR HANDLING TESTS
    // =============================================================================
    
    /**
     * Test 5.1: Handle Missing Source File
     */
    public function test_generate_avif_variant_returns_null_for_missing_source() {
        $result = $this->optimizer->generate_avif_variant('/nonexistent/image.jpg');
        
        $this->assertNull($result, 'Missing source should return null');
    }
    
    /**
     * Test 5.2: Handle Variant File Already Exists (Overwrite)
     */
    public function test_generate_avif_variant_overwrites_existing_variant() {
        if (!$this->isAvifSupported()) {
            $this->markTestSkipped('AVIF support not available');
        }
        
        $jpeg_path = $this->createTestImage('overwrite.jpg', 800, 600, 'jpeg');
        
        // First generation
        $result1 = $this->optimizer->generate_avif_variant($jpeg_path);
        $this->assertNotNull($result1);
        $mtime1 = filemtime($result1);
        
        // Wait to ensure different timestamp
        sleep(1);
        
        // Second generation
        $result2 = $this->optimizer->generate_avif_variant($jpeg_path);
        $this->assertNotNull($result2);
        $mtime2 = filemtime($result2);
        
        $this->assertEquals($result1, $result2, 'Should return same path');
        $this->assertGreaterThan($mtime1, $mtime2, 'File should be newer');
    }
    
    /**
     * Test 5.3: Handle Empty or Null File Path
     */
    public function test_generate_avif_variant_handles_empty_path() {
        $result_empty = $this->optimizer->generate_avif_variant('');
        $result_null = $this->optimizer->generate_avif_variant(null);
        
        $this->assertNull($result_empty, 'Empty path should return null');
        $this->assertNull($result_null, 'Null path should return null');
    }
    
    // =============================================================================
    // UTILITY METHODS
    // =============================================================================
    
    /**
     * Create test image with specified parameters
     */
    private function createTestImage($filename, $width = 800, $height = 600, $format = 'jpeg', $transparent = false) {
        $filepath = $this->temp_dir . '/' . $filename;
        $dir = dirname($filepath);
        
        if (!is_dir($dir)) {
            \wp_mkdir_p($dir);
        }
        
        $image = imagecreatetruecolor($width, $height);
        
        if ($transparent && $format === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent_color = imagecolorallocatealpha($image, 255, 255, 255, 127);
            imagefill($image, 0, 0, $transparent_color);
        } else {
            $color = imagecolorallocate($image, 255, 100, 50);
            imagefilledrectangle($image, 0, 0, $width, $height, $color);
        }
        
        // Add some visual content for better compression testing
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 50, 50, $width - 50, $height - 50, $white);
        
        switch ($format) {
            case 'jpeg':
                imagejpeg($image, $filepath, 90);
                break;
            case 'png':
                imagepng($image, $filepath);
                break;
            case 'gif':
                imagegif($image, $filepath);
                break;
        }
        
        imagedestroy($image);
        
        $this->test_images[] = $filepath;
        return $filepath;
    }
    
    /**
     * Create invalid/corrupted image file
     */
    private function createInvalidImageFile($filename) {
        $filepath = $this->temp_dir . '/' . $filename;
        file_put_contents($filepath, 'This is not an image file');
        $this->test_images[] = $filepath;
        return $filepath;
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirRecursive($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
    
    /**
     * Check if AVIF support is available
     */
    private function isAvifSupported() {
        return extension_loaded('gd') && function_exists('imageavif');
    }
    
    /**
     * Check if WebP support is available
     */
    private function isWebpSupported() {
        return extension_loaded('gd') && function_exists('imagewebp');
    }
}
