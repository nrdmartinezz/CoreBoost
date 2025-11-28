## Video Hero Fallback LCP Optimization Implementation

### Overview
Implemented YouTube video hero thumbnail extraction and preloading to optimize LCP (Largest Contentful Paint) for pages with Elementor video background hero sections.

### Changes Made

#### 1. New Preload Method: `video_fallback`
Added a new preload method option in `preload_hero_images()` that prioritizes video hero fallback thumbnails:
- **File**: `includes/public/class-hero-optimizer.php`
- **Method**: `preload_video_hero_fallback()`
- **Location in methods array**: `'video_fallback' => 'preload_video_hero_fallback'`

When selected, this method automatically:
1. Detects if the first hero element has a video background
2. Extracts the YouTube video ID from the video URL
3. Generates a thumbnail URL
4. Falls back to static background image if no video hero is detected
5. Preloads the image with `fetchpriority="high"` in `<head>`

#### 2. New Helper Methods

**`get_video_hero_fallback_image($elements)`**
- Checks if the first element (hero section) has `background_video_link` setting
- Returns thumbnail URL for YouTube videos or null for Vimeo
- Uses `detect_video_type()` to determine video platform

**`extract_youtube_thumbnail_url($url)`**
- Extracts YouTube video ID from various URL formats:
  - `https://youtu.be/VIDEO_ID`
  - `https://youtube.com/watch?v=VIDEO_ID`
  - `https://youtube.com/embed/VIDEO_ID`
  - `https://youtube.com/v/VIDEO_ID`
- Generates thumbnail URL using `hqdefault.jpg` (480x360)
  - Balance between quality and fast loading
  - Always available for all YouTube videos
  - 10-15KB typical file size
- Returns format: `https://img.youtube.com/vi/{VIDEO_ID}/hqdefault.jpg`

**`extract_vimeo_thumbnail_url($url)`**
- Stub implementation for future expansion
- Currently returns null (Vimeo requires API call)
- Falls back to static image when called

### How It Works

#### Elementor Data Structure
The plugin now processes Elementor containers with video backgrounds:

```php
{
    "elType": "container",
    "settings": {
        "background_background": "video",
        "background_video_link": "https://youtu.be/fLvcbdgiB2U",
        "background_play_on_mobile": "yes"
    }
}
```

#### Execution Flow
1. `preload_video_hero_fallback()` is called during `wp_head` (priority 1)
2. Retrieves Elementor data for current post
3. Calls `get_video_hero_fallback_image()` to check first element
4. If video hero found → extracts YouTube ID → generates thumbnail URL
5. If no video hero → falls back to `find_hero_background_image()` for static images
6. Outputs preload tag: `<link rel="preload" href="thumbnail_url" as="image" fetchpriority="high">`
7. Browser prioritizes downloading thumbnail immediately

#### LCP Optimization Impact
- **Before**: YouTube iframe loads asynchronously, no visible LCP candidate
  - Browser doesn't discover image resource in initial HTML
  - Image lazy-loads when iframe renders
  - LCP metric shows poor performance

- **After**: Thumbnail preloaded with `fetchpriority="high"`
  - Browser discovers and prioritizes thumbnail in initial HTML request
  - Thumbnail loads immediately, appears in first 2.5 seconds typically
  - LCP metric improves significantly
  - Fixes "Request is discoverable in initial document" audit issue

### URL Extraction Logic

The YouTube ID extraction uses cascading regex patterns:

1. **youtu.be format**: `https://youtu.be/VIDEO_ID`
   ```regex
   /youtu\.be\/([a-zA-Z0-9_-]{11})/
   ```

2. **watch?v= format**: `https://youtube.com/watch?v=VIDEO_ID&t=123`
   ```regex
   /youtube\.com.*[?&]v=([a-zA-Z0-9_-]{11})/
   ```

3. **embed format**: `https://youtube.com/embed/VIDEO_ID`
   ```regex
   /youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/
   ```

4. **fallback v/ format**: `https://youtube.com/v/VIDEO_ID`
   ```regex
   /\/(?:v|e(?:mbed)?)\/([a-zA-Z0-9_-]{11})/
   ```

### Thumbnail Quality Decision

**Selected**: `hqdefault.jpg` (480x360px, ~10-15KB)

Trade-offs considered:
- `default.jpg` (120x90) - Too small, poor quality
- `mqdefault.jpg` (320x180) - Acceptable quality, ~5KB
- **`hqdefault.jpg` (480x360) - Best balance ✓**
  - Sufficient quality for hero backgrounds
  - Reliable availability across all videos
  - Fast loading (10-15KB typical)
  - Non-progressive JPEG, instant rendering
- `sddefault.jpg` (640x480) - Slower (50-100KB), occasional unavailability
- `maxresdefault.jpg` (1280x720+) - Too large, might not exist

### Configuration
To enable this feature, set in CoreBoost settings:
```
Preload Method: "video_fallback"
```

### Fallback Chain
1. ✓ Video hero thumbnail (YouTube)
2. → Static background image
3. → Featured image
4. → Custom field

### Performance Considerations
- **No performance penalty**: Regex matching happens once per page load
- **Cached**: Results cached in transient for 1 hour
- **Early termination**: Only checks first element (hero section)
- **Minimal file size**: YouTube thumbnails are ~10-15KB
- **Early output**: Preload tag in `wp_head` priority 1 (very first)

### Testing

All YouTube URL formats tested and verified:
- ✓ `https://youtu.be/fLvcbdgiB2U`
- ✓ `https://www.youtube.com/watch?v=fLvcbdgiB2U`
- ✓ `https://youtube.com/watch?v=fLvcbdgiB2U&t=10s`
- ✓ `https://www.youtube.com/embed/fLvcbdgiB2U`

### Future Enhancements
1. **Vimeo support** - Would require async API calls to fetch thumbnail URLs
2. **Poster image support** - Parse Elementor's `background_video_poster` setting if available
3. **Multiple video sections** - Extend to detect multiple video heroes on same page
4. **Custom thumbnail override** - Allow admin to manually specify fallback images per page

### Related Code
- `find_background_videos()` - Detects YouTube/Vimeo background videos (used by smart YouTube blocking)
- `detect_video_type()` - Identifies video platform (youtube/vimeo/hosted/unknown)
- `output_preload_tag()` - Outputs preload link with fetchpriority="high"
- `detect_elementor_background_videos()` - Public method used by Resource_Remover class
