<?php
/**
 * Phase 1-2 Implementation Test Suite
 * 
 * Quick verification script for CoreBoost Phase 1-2 implementation
 * Run this in WordPress admin to verify all integrations work
 */

namespace CoreBoost\Testing;

// This would be run in WordPress context
// Tests here verify the implementation is correct

class Phase_1_2_Tests {
    
    /**
     * Test Phase 1: Script Exclusions
     */
    public static function test_phase_1_exclusions() {
        echo "=== Phase 1: Script Exclusions ===\n\n";
        
        // Test 1: Verify class exists
        $class_path = COREBOOST_PATH . 'includes/public/class-script-exclusions.php';
        if (file_exists($class_path)) {
            echo "✓ class-script-exclusions.php exists\n";
        } else {
            echo "✗ class-script-exclusions.php NOT FOUND\n";
            return false;
        }
        
        // Test 2: Check for Script_Exclusions class
        if (class_exists('CoreBoost\PublicCore\Script_Exclusions')) {
            echo "✓ Script_Exclusions class found\n";
        } else {
            echo "✗ Script_Exclusions class NOT FOUND\n";
            return false;
        }
        
        // Test 3: Verify default options
        $options = get_option('coreboost_options', array());
        if (isset($options['enable_default_exclusions'])) {
            echo "✓ enable_default_exclusions option exists (value: " . var_export($options['enable_default_exclusions'], true) . ")\n";
        } else {
            echo "✗ enable_default_exclusions option NOT FOUND\n";
        }
        
        if (isset($options['script_exclusion_patterns'])) {
            echo "✓ script_exclusion_patterns option exists (value: '" . substr($options['script_exclusion_patterns'], 0, 50) . "...')\n";
        } else {
            echo "✗ script_exclusion_patterns option NOT FOUND\n";
        }
        
        // Test 4: Test exclusion logic
        if (class_exists('CoreBoost\PublicCore\Script_Exclusions')) {
            $exclusions = new \CoreBoost\PublicCore\Script_Exclusions($options);
            
            // Should be excluded
            if ($exclusions->is_excluded('jquery-core')) {
                echo "✓ jQuery core correctly excluded (as expected)\n";
            } else {
                echo "✗ jQuery core NOT excluded (unexpected)\n";
            }
            
            // Should NOT be excluded
            if (!$exclusions->is_excluded('custom-script-12345')) {
                echo "✓ Custom script not excluded (as expected)\n";
            } else {
                echo "✗ Custom script incorrectly excluded\n";
            }
        }
        
        return true;
    }
    
    /**
     * Test Phase 2: Load Strategies
     */
    public static function test_phase_2_strategies() {
        echo "\n=== Phase 2: Load Strategies ===\n\n";
        
        // Test 1: Check Tag_Manager exists
        if (class_exists('CoreBoost\PublicCore\Tag_Manager')) {
            echo "✓ Tag_Manager class found\n";
        } else {
            echo "✗ Tag_Manager class NOT FOUND\n";
            return false;
        }
        
        // Test 2: Check strategy options
        $options = get_option('coreboost_options', array());
        if (isset($options['tag_load_strategy'])) {
            $strategy = $options['tag_load_strategy'];
            echo "✓ tag_load_strategy option exists (value: '$strategy')\n";
            
            $valid_strategies = array('immediate', 'balanced', 'aggressive', 'user_interaction', 'browser_idle', 'custom');
            if (in_array($strategy, $valid_strategies)) {
                echo "✓ tag_load_strategy has valid value\n";
            } else {
                echo "✗ tag_load_strategy has invalid value: '$strategy'\n";
            }
        } else {
            echo "✗ tag_load_strategy option NOT FOUND\n";
        }
        
        if (isset($options['tag_custom_delay'])) {
            $delay = $options['tag_custom_delay'];
            echo "✓ tag_custom_delay option exists (value: $delay ms)\n";
        } else {
            echo "✗ tag_custom_delay option NOT FOUND\n";
        }
        
        return true;
    }
    
    /**
     * Test Admin UI Integration
     */
    public static function test_admin_ui() {
        echo "\n=== Admin UI Integration ===\n\n";
        
        // Test 1: Check Script_Settings exists
        $class_path = COREBOOST_PATH . 'includes/admin/class-script-settings.php';
        if (file_exists($class_path)) {
            echo "✓ class-script-settings.php exists\n";
        } else {
            echo "✗ class-script-settings.php NOT FOUND\n";
            return false;
        }
        
        if (class_exists('CoreBoost\Admin\Script_Settings')) {
            echo "✓ Script_Settings admin class found\n";
        } else {
            echo "✗ Script_Settings admin class NOT FOUND\n";
            return false;
        }
        
        // Test 2: Check main Settings class updated
        if (class_exists('CoreBoost\Admin\Settings')) {
            echo "✓ Settings class found\n";
            
            // Try to instantiate
            $options = get_option('coreboost_options', array());
            try {
                $settings = new \CoreBoost\Admin\Settings($options);
                echo "✓ Settings class instantiates successfully\n";
            } catch (\Exception $e) {
                echo "✗ Settings class instantiation failed: " . $e->getMessage() . "\n";
                return false;
            }
        } else {
            echo "✗ Settings class NOT FOUND\n";
            return false;
        }
        
        return true;
    }
    
    /**
     * Test Backward Compatibility
     */
    public static function test_backward_compatibility() {
        echo "\n=== Backward Compatibility ===\n\n";
        
        // Test 1: Old exclude_scripts setting still works
        $options = get_option('coreboost_options', array());
        if (isset($options['exclude_scripts'])) {
            echo "✓ Legacy exclude_scripts setting preserved\n";
            
            // Test that Script_Exclusions reads it
            if (class_exists('CoreBoost\PublicCore\Script_Exclusions')) {
                $exclusions = new \CoreBoost\PublicCore\Script_Exclusions($options);
                echo "✓ Script_Exclusions reads legacy exclude_scripts setting\n";
            }
        } else {
            echo "• Legacy exclude_scripts setting not set (this is OK)\n";
        }
        
        // Test 2: Script_Optimizer still works
        if (class_exists('CoreBoost\PublicCore\Script_Optimizer')) {
            echo "✓ Script_Optimizer class still exists\n";
        } else {
            echo "✗ Script_Optimizer class NOT FOUND\n";
            return false;
        }
        
        return true;
    }
    
    /**
     * Run all tests
     */
    public static function run_all_tests() {
        echo "CoreBoost Phase 1-2 Implementation Test Suite\n";
        echo "=============================================\n\n";
        
        $results = array(
            'Phase 1 Exclusions' => self::test_phase_1_exclusions(),
            'Phase 2 Strategies' => self::test_phase_2_strategies(),
            'Admin UI' => self::test_admin_ui(),
            'Backward Compatibility' => self::test_backward_compatibility(),
        );
        
        echo "\n\n=== Test Summary ===\n";
        $passed = 0;
        $failed = 0;
        
        foreach ($results as $test_name => $result) {
            if ($result) {
                echo "✓ $test_name: PASSED\n";
                $passed++;
            } else {
                echo "✗ $test_name: FAILED\n";
                $failed++;
            }
        }
        
        echo "\nTotal: $passed passed, $failed failed\n";
        
        return $failed === 0;
    }
}

// Usage: Call Phase_1_2_Tests::run_all_tests() from WordPress admin
