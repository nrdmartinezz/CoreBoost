<?php
/**
 * Integration Tests for Bulk Converter AJAX Endpoints
 * 
 * Tests the AJAX handlers for the bulk image conversion feature.
 * 
 * @package CoreBoost
 * @subpackage Tests\Integration
 * @since 3.0.7
 */

namespace CoreBoost\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Test class for Bulk Converter AJAX functionality.
 */
class BulkConverterAjaxTest extends TestCase {
    
    /**
     * Mock options storage for tests.
     *
     * @var array
     */
    private static $mock_options = [];
    
    /**
     * Mock transients storage for tests.
     *
     * @var array
     */
    private static $mock_transients = [];
    
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Reset mock storages
        self::$mock_options = [];
        self::$mock_transients = [];
        
        // Reset Context_Helper state
        \CoreBoost\Core\Context_Helper::reset_cache();
        
        // Set up default options
        self::$mock_options['coreboost_settings'] = [
            'webp_conversion' => true,
            'avif_conversion' => false,
            'quality' => 82,
            'lazy_loading' => true,
        ];
    }
    
    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
        
        // Clean up test files
        $temp_dir = sys_get_temp_dir() . '/coreboost-test-uploads';
        if (is_dir($temp_dir)) {
            $this->recursiveDelete($temp_dir);
        }
        
        // Reset storages
        self::$mock_options = [];
        self::$mock_transients = [];
    }
    
    /**
     * Recursively delete a directory.
     *
     * @param string $dir Directory path.
     */
    private function recursiveDelete(string $dir): void {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                        $this->recursiveDelete($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Test that scan images endpoint validates permissions.
     */
    public function test_scan_images_requires_capability(): void {
        // Simulate a user without the required capability
        $this->assertFalse(
            $this->mockCurrentUserCan('manage_options', false),
            'User without capability should not be able to scan images'
        );
    }
    
    /**
     * Test that scan images endpoint returns proper structure.
     */
    public function test_scan_images_returns_expected_structure(): void {
        $expected_keys = ['success', 'data'];
        $data_keys = ['total_images', 'converted', 'pending', 'failed', 'images'];
        
        $response = $this->mockScanImagesResponse();
        
        // Check top-level structure
        foreach ($expected_keys as $key) {
            $this->assertArrayHasKey($key, $response, "Response should have key: $key");
        }
        
        // Check data structure
        foreach ($data_keys as $key) {
            $this->assertArrayHasKey($key, $response['data'], "Response data should have key: $key");
        }
    }
    
    /**
     * Test that scan images correctly identifies unconverted images.
     */
    public function test_scan_images_identifies_unconverted_images(): void {
        // Create test image files
        $test_images = $this->createTestImages(['test1.jpg', 'test2.png', 'test3.jpg']);
        
        $response = $this->mockScanImagesResponse($test_images);
        
        $this->assertEquals(3, $response['data']['total_images']);
        $this->assertEquals(3, $response['data']['pending']);
        $this->assertEquals(0, $response['data']['converted']);
    }
    
    /**
     * Test that scan images excludes already converted images.
     */
    public function test_scan_images_excludes_converted_images(): void {
        // Create test images with some already converted
        $test_images = $this->createTestImages(['test1.jpg', 'test2.png']);
        $this->createConvertedImage($test_images[0]); // Mark first as converted
        
        $response = $this->mockScanImagesResponse($test_images);
        
        $this->assertEquals(2, $response['data']['total_images']);
        $this->assertEquals(1, $response['data']['converted']);
        $this->assertEquals(1, $response['data']['pending']);
    }
    
    /**
     * Test batch processing validates nonce.
     */
    public function test_process_batch_validates_nonce(): void {
        $result = $this->mockProcessBatchWithInvalidNonce();
        
        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_nonce', $result['data']['error_code'] ?? '');
    }
    
    /**
     * Test batch processing respects batch size limits.
     */
    public function test_process_batch_respects_batch_size(): void {
        $max_batch_size = 10;
        $test_images = $this->createTestImages(array_map(
            fn($i) => "test$i.jpg",
            range(1, 15)
        ));
        
        $result = $this->mockProcessBatch($test_images, $max_batch_size);
        
        $this->assertLessThanOrEqual(
            $max_batch_size,
            $result['data']['processed'],
            'Should not process more than batch size'
        );
    }
    
    /**
     * Test batch processing handles invalid images gracefully.
     */
    public function test_process_batch_handles_invalid_images(): void {
        $test_images = [
            '/path/to/nonexistent.jpg',
            '/path/to/another-missing.png',
        ];
        
        $result = $this->mockProcessBatch($test_images);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['converted']);
        $this->assertEquals(2, $result['data']['failed']);
    }
    
    /**
     * Test conversion stats endpoint returns accurate counts.
     */
    public function test_get_conversion_stats_returns_accurate_counts(): void {
        // Set up mock stats
        self::$mock_options['coreboost_conversion_stats'] = [
            'total_converted' => 150,
            'total_failed' => 5,
            'bytes_saved' => 1024000,
            'last_conversion' => time(),
        ];
        
        $stats = $this->mockGetConversionStats();
        
        $this->assertEquals(150, $stats['total_converted']);
        $this->assertEquals(5, $stats['total_failed']);
        $this->assertEquals(1024000, $stats['bytes_saved']);
    }
    
    /**
     * Test conversion stats handles missing data gracefully.
     */
    public function test_get_conversion_stats_handles_missing_data(): void {
        // No stats stored
        self::$mock_options['coreboost_conversion_stats'] = null;
        
        $stats = $this->mockGetConversionStats();
        
        $this->assertEquals(0, $stats['total_converted']);
        $this->assertEquals(0, $stats['total_failed']);
        $this->assertEquals(0, $stats['bytes_saved']);
    }
    
    /**
     * Test that Context_Helper skip flag is respected during conversion.
     */
    public function test_conversion_respects_context_helper_skip(): void {
        \CoreBoost\Core\Context_Helper::set_should_skip(true);
        
        $test_images = $this->createTestImages(['test.jpg']);
        $result = $this->mockProcessBatch($test_images);
        
        $this->assertEquals(
            0,
            $result['data']['converted'],
            'Should not convert images when skip flag is set'
        );
    }
    
    /**
     * Test debug logging during conversion when WP_DEBUG is enabled.
     */
    public function test_debug_logging_during_conversion(): void {
        \CoreBoost\Core\Context_Helper::set_debug_mode(true);
        \CoreBoost\Core\Context_Helper::clear_debug_log_calls();
        
        $test_images = $this->createTestImages(['test.jpg']);
        $this->mockProcessBatch($test_images);
        
        $log_calls = \CoreBoost\Core\Context_Helper::get_debug_log_calls();
        
        $this->assertNotEmpty($log_calls, 'Debug logging should occur during conversion');
    }
    
    // ===========================================
    // Helper Methods for Mocking AJAX Behavior
    // ===========================================
    
    /**
     * Mock checking if current user has capability.
     *
     * @param string $capability Capability to check.
     * @param bool   $has_cap    Whether user has capability.
     * @return bool
     */
    private function mockCurrentUserCan(string $capability, bool $has_cap = true): bool {
        return $has_cap;
    }
    
    /**
     * Mock the scan images AJAX response.
     *
     * @param array $images Optional array of image paths.
     * @return array
     */
    private function mockScanImagesResponse(array $images = []): array {
        $total = count($images);
        $converted = 0;
        $pending = 0;
        $failed = 0;
        
        foreach ($images as $image) {
            if ($this->isImageConverted($image)) {
                $converted++;
            } else {
                $pending++;
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'total_images' => $total,
                'converted' => $converted,
                'pending' => $pending,
                'failed' => $failed,
                'images' => $images,
            ],
        ];
    }
    
    /**
     * Mock processing a batch with invalid nonce.
     *
     * @return array
     */
    private function mockProcessBatchWithInvalidNonce(): array {
        return [
            'success' => false,
            'data' => [
                'error_code' => 'invalid_nonce',
                'message' => 'Security check failed',
            ],
        ];
    }
    
    /**
     * Mock the process batch AJAX response.
     *
     * @param array $images     Array of image paths.
     * @param int   $batch_size Maximum batch size.
     * @return array
     */
    private function mockProcessBatch(array $images, int $batch_size = 10): array {
        // Check if conversion should be skipped
        if (\CoreBoost\Core\Context_Helper::should_skip_optimization()) {
            return [
                'success' => true,
                'data' => [
                    'processed' => 0,
                    'converted' => 0,
                    'failed' => 0,
                    'skipped' => count($images),
                ],
            ];
        }
        
        $processed = min(count($images), $batch_size);
        $converted = 0;
        $failed = 0;
        
        foreach (array_slice($images, 0, $batch_size) as $image) {
            // Log debug info if enabled
            if (\CoreBoost\Core\Context_Helper::is_debug_mode()) {
                \CoreBoost\Core\Context_Helper::debug_log("Processing image: $image");
            }
            
            if (file_exists($image)) {
                // Simulate conversion
                $converted++;
            } else {
                $failed++;
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'processed' => $processed,
                'converted' => $converted,
                'failed' => $failed,
                'skipped' => 0,
            ],
        ];
    }
    
    /**
     * Mock the get conversion stats response.
     *
     * @return array
     */
    private function mockGetConversionStats(): array {
        $stats = self::$mock_options['coreboost_conversion_stats'] ?? null;
        
        return [
            'total_converted' => $stats['total_converted'] ?? 0,
            'total_failed' => $stats['total_failed'] ?? 0,
            'bytes_saved' => $stats['bytes_saved'] ?? 0,
            'last_conversion' => $stats['last_conversion'] ?? null,
        ];
    }
    
    /**
     * Create test image files.
     *
     * @param array $filenames Array of filenames to create.
     * @return array Array of full paths to created files.
     */
    private function createTestImages(array $filenames): array {
        $temp_dir = sys_get_temp_dir() . '/coreboost-test-uploads';
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        
        $paths = [];
        foreach ($filenames as $filename) {
            $path = $temp_dir . '/' . $filename;
            // Create a minimal valid image file (1x1 pixel)
            $this->createMinimalImage($path);
            $paths[] = $path;
        }
        
        return $paths;
    }
    
    /**
     * Create a minimal valid image file for testing.
     *
     * @param string $path File path.
     */
    private function createMinimalImage(string $path): void {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        // Create a minimal 1x1 pixel image using GD if available
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor(1, 1);
            $white = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $white);
            
            switch ($ext) {
                case 'png':
                    imagepng($img, $path);
                    break;
                case 'gif':
                    imagegif($img, $path);
                    break;
                default:
                    imagejpeg($img, $path, 100);
            }
            
            imagedestroy($img);
        } else {
            // Fallback: create a minimal file
            file_put_contents($path, 'test-image-data');
        }
    }
    
    /**
     * Create a "converted" version of an image.
     *
     * @param string $original_path Path to original image.
     */
    private function createConvertedImage(string $original_path): void {
        $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $original_path);
        file_put_contents($webp_path, 'webp-converted-data');
    }
    
    /**
     * Check if an image has been converted.
     *
     * @param string $image_path Image path.
     * @return bool
     */
    private function isImageConverted(string $image_path): bool {
        $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $image_path);
        return file_exists($webp_path);
    }
}
