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
    
    /**
     * Render card selector field with tooltips
     * Cards display in a horizontal row with radio button selection
     *
     * @param string $name Field name
     * @param mixed $value Current selected value
     * @param array $cards Array of card configurations with keys:
     *                     - key: Option value
     *                     - title: Card title
     *                     - icon: Dashicon class (e.g., 'dashicons-admin-generic')
     *                     - excerpt: Short description (1 line)
     *                     - tooltip: Detailed explanation for info bubble
     */
    public static function render_card_selector($name, $value, $cards) {
        echo '<div class="coreboost-card-selector" role="radiogroup" aria-label="' . esc_attr__('Select preload method', 'coreboost') . '">';
        
        foreach ($cards as $card) {
            $is_selected = ($value === $card['key']);
            $card_id = 'coreboost-card-' . esc_attr($name) . '-' . esc_attr($card['key']);
            $tooltip_id = $card_id . '-tooltip';
            
            echo '<div class="coreboost-method-card' . ($is_selected ? ' selected' : '') . '" data-value="' . esc_attr($card['key']) . '">';
            
            // Hidden radio input for form submission
            echo '<input type="radio" name="coreboost_options[' . esc_attr($name) . ']" id="' . $card_id . '" ';
            echo 'value="' . esc_attr($card['key']) . '"' . checked($is_selected, true, false) . ' ';
            echo 'class="coreboost-card-radio" aria-describedby="' . $tooltip_id . '">';
            
            // Card content wrapper
            echo '<label for="' . $card_id . '" class="coreboost-card-content">';
            
            // Icon
            echo '<span class="coreboost-card-icon"><span class="dashicons ' . esc_attr($card['icon']) . '"></span></span>';
            
            // Title with info bubble
            echo '<span class="coreboost-card-title">';
            echo esc_html($card['title']);
            
            // Info bubble with tooltip
            if (!empty($card['tooltip'])) {
                echo '<button type="button" class="coreboost-info-trigger" aria-expanded="false" aria-describedby="' . $tooltip_id . '">';
                echo '<span class="dashicons dashicons-info-outline"></span>';
                echo '<span class="screen-reader-text">' . esc_html__('More information', 'coreboost') . '</span>';
                echo '</button>';
                echo '<div id="' . $tooltip_id . '" class="coreboost-tooltip" role="tooltip" aria-hidden="true">';
                echo '<div class="coreboost-tooltip-content">' . wp_kses_post($card['tooltip']) . '</div>';
                echo '<div class="coreboost-tooltip-arrow"></div>';
                echo '</div>';
            }
            
            echo '</span>';
            
            // Excerpt
            echo '<span class="coreboost-card-excerpt">' . esc_html($card['excerpt']) . '</span>';
            
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
    }
}
