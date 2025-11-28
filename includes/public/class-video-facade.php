<?php
/**
 * Video facade system for above-the-fold video widgets
 * Replaces embedded YouTube/Vimeo with click-to-play facades
 *
 * @package CoreBoost
 * @since 2.1.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Debug_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Video_Facade
 * Handles video facade replacement for performance optimization
 */
class Video_Facade {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Loader instance
     *
     * @var \CoreBoost\Loader
     */
    private $loader;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        $this->define_hooks();
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        // Process output to replace video iframes with facades
        $this->loader->add_filter('wp_footer', $this, 'add_video_facade_script', 999);
    }
    
    /**
     * Check if video facade should be enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return isset($this->options['smart_video_facades']) && $this->options['smart_video_facades'];
    }
    
    /**
     * Extract video ID from YouTube/Vimeo URL
     *
     * @param string $url Video URL
     * @return array|false Array with 'id' and 'type' keys, or false
     */
    public function extract_video_id($url) {
        // YouTube formats
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\s]{11})/', $url, $matches)) {
            return array(
                'id' => $matches[1],
                'type' => 'youtube'
            );
        }
        
        // Vimeo formats
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
            return array(
                'id' => $matches[1],
                'type' => 'vimeo'
            );
        }
        
        return false;
    }
    
    /**
     * Get YouTube thumbnail URL
     *
     * @param string $video_id YouTube video ID
     * @return string Thumbnail URL
     */
    public function get_youtube_thumbnail($video_id) {
        // Try maxresdefault first (highest quality), fallback to sddefault, then hqdefault
        return "https://i.ytimg.com/vi/{$video_id}/maxresdefault.jpg";
    }
    
    /**
     * Get Vimeo thumbnail URL
     *
     * @param string $video_id Vimeo video ID
     * @return string Thumbnail URL
     */
    public function get_vimeo_thumbnail($video_id) {
        // Vimeo requires API call, use generic fallback for now
        return "https://i.vimeocdn.com/video/{$video_id}.jpg";
    }
    
    /**
     * Generate facade HTML for a video
     *
     * @param array $video_data Video data (id, type, url, title)
     * @return string Facade HTML
     */
    public function generate_facade_html($video_data) {
        $video_id = $video_data['id'];
        $video_type = $video_data['type'];
        $video_url = $video_data['url'];
        $title = isset($video_data['title']) ? esc_attr($video_data['title']) : 'Play video';
        
        // Get thumbnail
        if ($video_type === 'youtube') {
            $thumbnail = $this->get_youtube_thumbnail($video_id);
        } elseif ($video_type === 'vimeo') {
            $thumbnail = $this->get_vimeo_thumbnail($video_id);
        } else {
            return '';
        }
        
        $facade_id = 'coreboost-video-facade-' . sanitize_html_class($video_id);
        $encoded_url = esc_attr($video_url);
        
        return <<<HTML
<div class="coreboost-video-facade" id="{$facade_id}" data-video-id="{$video_id}" data-video-type="{$video_type}" data-video-url="{$encoded_url}" style="position: relative; display: block; width: 100%; padding-bottom: 56.25%; cursor: pointer; overflow: hidden; background: #000;">
    <img src="{$thumbnail}" alt="{$title}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;" loading="lazy" />
    <button class="coreboost-video-play-btn" aria-label="{$title}" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60px; height: 60px; background: rgba(0, 0, 0, 0.7); border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.3s ease; z-index: 10;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff" xmlns="http://www.w3.org/2000/svg">
            <polygon points="5 3 19 12 5 21"></polygon>
        </svg>
    </button>
</div>
HTML;
    }
    
    /**
     * Add video facade script to footer
     */
    public function add_video_facade_script() {
        if (!$this->is_enabled()) {
            return;
        }
        
        if (is_admin()) {
            return;
        }
        
        ?>
        <script id="coreboost-video-facade-script">
        (function() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initVideoFacades);
            } else {
                initVideoFacades();
            }
            
            function initVideoFacades() {
                const facades = document.querySelectorAll('.coreboost-video-facade');
                
                facades.forEach(function(facade) {
                    const playBtn = facade.querySelector('.coreboost-video-play-btn');
                    const videoType = facade.getAttribute('data-video-type');
                    const videoId = facade.getAttribute('data-video-id');
                    const videoUrl = facade.getAttribute('data-video-url');
                    
                    // Hover effect
                    playBtn.addEventListener('mouseenter', function() {
                        playBtn.style.background = 'rgba(0, 0, 0, 0.9)';
                    });
                    
                    playBtn.addEventListener('mouseleave', function() {
                        playBtn.style.background = 'rgba(0, 0, 0, 0.7)';
                    });
                    
                    // Click to load
                    facade.addEventListener('click', function() {
                        replaceWithIframe(facade, videoType, videoId, videoUrl);
                    });
                    
                    // Track impression for analytics if needed
                    if (window.gtag) {
                        window.gtag('event', 'video_facade_impression', {
                            'video_id': videoId,
                            'video_type': videoType
                        });
                    }
                });
            }
            
            function replaceWithIframe(facade, videoType, videoId, videoUrl) {
                let iframeHtml = '';
                
                if (videoType === 'youtube') {
                    iframeHtml = '<iframe width="100%" height="100%" src="https://www.youtube.com/embed/' + videoId + '?autoplay=1" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></iframe>';
                } else if (videoType === 'vimeo') {
                    iframeHtml = '<iframe src="https://player.vimeo.com/video/' + videoId + '?autoplay=1" width="100%" height="100%" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></iframe>';
                }
                
                if (iframeHtml) {
                    facade.innerHTML = iframeHtml;
                    facade.style.paddingBottom = '56.25%';
                    
                    // Track play event for analytics
                    if (window.gtag) {
                        window.gtag('event', 'video_facade_play', {
                            'video_id': videoId,
                            'video_type': videoType
                        });
                    }
                }
            }
        })();
        </script>
        
        <style>
        .coreboost-video-facade {
            background-color: #000;
        }
        
        .coreboost-video-facade img {
            -webkit-user-select: none;
            user-select: none;
            pointer-events: none;
        }
        
        .coreboost-video-play-btn:hover {
            background: rgba(0, 0, 0, 0.9) !important;
        }
        
        .coreboost-video-play-btn:active {
            transform: translate(-50%, -50%) scale(0.95);
        }
        </style>
        <?php
    }
}
