<?php
/**
 * Form field rendering functionality
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Field_Renderer
 */
class Field_Renderer {
    
    /**
     * Render checkbox field
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param bool $default Default value
     * @param string $description Field description
     */
    public static function render_checkbox($name, $value, $default, $description) {
        $checked = isset($value) ? $value : $default;
        echo '<input type="checkbox" name="coreboost_options[' . esc_attr($name) . ']" value="1"' . checked($checked, true, false) . '>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
    
    /**
     * Render textarea field
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param int $rows Number of rows
     * @param string $description Field description
     * @param string $class CSS class
     */
    public static function render_textarea($name, $value, $rows, $description, $class = 'large-text') {
        $value = isset($value) ? $value : '';
        echo '<textarea name="coreboost_options[' . esc_attr($name) . ']" rows="' . esc_attr($rows) . '" cols="50" class="' . esc_attr($class) . '">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
    
    /**
     * Render select field
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array $options Select options
     * @param mixed $default Default value
     * @param string $description Field description
     */
    public static function render_select($name, $value, $options, $default, $description) {
        $value = isset($value) ? $value : $default;
        echo '<select name="coreboost_options[' . esc_attr($name) . ']">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
}
