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
     * Render slider field
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @param int $step Step value
     * @param string $description Field description
     */
    public static function render_slider($name, $value, $min, $max, $step, $description) {
        $value = isset($value) ? (int)$value : (int)$min;
        echo '<div style="display: flex; align-items: center; gap: 15px;">';
        echo '<input type="range" name="coreboost_options[' . esc_attr($name) . ']" ';
        echo 'min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '" ';
        echo 'value="' . esc_attr($value) . '" ';
        echo 'style="width: 300px;" ';
        echo 'oninput="document.getElementById(\'' . esc_attr($name) . '_display\').textContent = this.value;">';
        echo '<span id="' . esc_attr($name) . '_display" style="min-width: 40px; text-align: center; font-weight: bold;">' . esc_html($value) . '</span>';
        echo '</div>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
    
    /**
     * Render select field (updated to accept description only)
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array $options Select options (key => label)
     * @param string $description Field description
     */
    public static function render_select($name, $value, $options, $description) {
        echo '<select name="coreboost_options[' . esc_attr($name) . ']">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
    
    /**
     * Render select field (legacy signature with default parameter)
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array $options Select options (key => label)
     * @param mixed $default Default value
     * @param string $description Field description
     */
    public static function render_select_with_default($name, $value, $options, $default, $description) {
        $value = isset($value) ? $value : $default;
        echo '<select name="coreboost_options[' . esc_attr($name) . ']">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
}
