# CoreBoost Phase 2: Advanced Image Optimization Implementation Plan

## Executive Summary

This document outlines the comprehensive Phase 2 implementation strategy for advanced image format optimization in CoreBoost. Phase 1 (basic image optimization) is complete and deployed. Phase 2 focuses on intelligent image format conversion (AVIF + WebP) with automated variant lifecycle management to achieve the PSI target of -92 KiB image optimization.

**Target**: Reduce image download size by 92-102 KiB through modern format delivery and intelligent caching.

---

## 1. Strategic Overview

### Phase 1 Status (COMPLETE ✅)
- Lazy loading enforcement (native `loading="lazy"`)
- Width/height attribute addition (CLS prevention)
- Aspect ratio CSS generation
- Async image decoding
- Active on staging: 3 lazy images, 4 async images
- **Status**: Production-ready and tested

### Phase 2 Objectives
1. **Image Format Optimization**: Convert JPEG images to AVIF (20-30% compression advantage)
2. **Browser Compatibility**: WebP fallback for older browsers
3. **Automated Lifecycle**: Upload hooks, delete hooks, weekly cleanup
4. **Storage Efficiency**: Intelligent variant management and orphan detection
5. **Performance**: On-demand lazy generation with output buffer integration

### Format Strategy Decision: AVIF + WebP (No WebP-only)

**Rationale**:
- AVIF compression advantage: 20-30% better than WebP for photography
- AVIF browser support: 95%+ of modern browsers (Chrome, Firefox, Safari 16+, Edge)
- WebP fallback: Required only for Safari <16 and older browsers (~5% of traffic)
- Storage cost: Minimal (typically 2-3 variants per image)
- Delivery mechanism: HTML5 `<picture>` element with `Accept` header detection

**Supported Browsers**:
| Format | Chrome | Firefox | Safari | Edge | Support |
|--------|--------|---------|--------|------|---------|
| AVIF   | ✅ 85+ | ✅ 93+  | ✅ 16+ | ✅ 85+ | ~95% |
| WebP   | ✅ 32+ | ✅ 65+  | ✅ 16+ | ✅ 18+ | ~99% |
| JPEG   | ✅ All | ✅ All  | ✅ All | ✅ All | 100% |

---

## 2. Architecture Design

### Core Components

#### 2.1 Image_Format_Optimizer (450 lines)
**File**: `includes/public/class-image-format-optimizer.php`

**Purpose**: Intelligent image format conversion and delivery

**Key Methods**:
```
1. detect_browser_format()
   - Parse Accept header from request
   - Returns: 'avif', 'webp', or 'jpeg' based on browser capability
   - Fallback: 'jpeg' for old browsers

2. should_optimize_image($image_url)
   - Check if image is JPEG (other formats skipped)
   - Verify dimensions > 300px (skip thumbnails)
   - Check image age (skip recently uploaded)
   - Returns: boolean

3. generate_avif_variant($image_path)
   - Use GD/ImageMagick library
   - Use on-demand lazy generation (first request slower, cached after)
   - Store in /variants/[image-id]/avif/[filename].avif
   - Returns: path to generated AVIF or null on error

4. generate_webp_variant($image_path)
   - Similar to AVIF generation
   - Store in /variants/[image-id]/webp/[filename].webp
   - Returns: path to generated WebP or null on error

5. get_variant_from_cache($image_id, $format)
   - Check if variant exists in /variants/[image-id]/[format]/
   - Returns: path if exists, null if not

6. render_picture_tag($original_url, $alt, $classes)
   - Create HTML5 <picture> element
   - Sources: AVIF (if available), WebP (if available), JPEG (fallback)
   - Include srcset for 1x/2x retina support
   - Returns: full <picture> tag HTML

7. queue_background_generation($image_id, $image_url)
   - Add to Action Scheduler queue for non-blocking generation
   - Process variants in background after page load
   - Returns: job ID for tracking

8. get_image_dimensions($image_path)
   - Extract width/height from image
   - Cache metadata in wp_postmeta
   - Returns: array ['width' => int, 'height' => int]

9. is_image_used_on_site($image_id)
   - Search post_content and post_meta for image URL/ID
   - Returns: boolean

10. calculate_size_savings($original_size, $avif_size, $webp_size)
    - Estimate compression savings from format conversion
    - Returns: array with per-format savings
```

**Integration Points**:
- Called from Image_Optimizer in process_inline_assets()
- Processes <img> tags before output buffer flush
- Generates variants on-demand or queues for background processing
- Caches results via transients (1 week TTL)

---

#### 2.2 Image_Variant_Lifecycle_Manager (350 lines)
**File**: `includes/public/class-image-variant-lifecycle-manager.php`

**Purpose**: Automate variant creation, deletion, and cleanup

**Key Methods**:
```
1. on_image_upload($attachment_id)
   - Hook: add_action('add_attachment', ...)
   - Triggered when new image uploaded to media library
   - Queue AVIF/WebP generation in background
   - Log: "Queued variant generation for image #{$id}"
   - Returns: none

2. on_image_delete($attachment_id)
   - Hook: delete_attachment action (runs before deletion)
   - Find all variant files for this image
   - Delete from /variants/[image-id]/ directory
   - Clean up metadata from wp_postmeta
   - Log: "Deleted {$count} variants for image #{$id}"
   - Returns: none

3. on_image_edit($attachment_id)
   - Hook: wp_generate_attachment_metadata filter
   - Detect image changes (file replacement, metadata update)
   - Flag existing variants for regeneration
   - Queue new variant generation
   - Log: "Flagged variant regeneration for image #{$id}"
   - Returns: attachment metadata (pass-through)

4. cleanup_orphaned_variants()
   - WP-Cron: weekly (wp_schedule_event every 7 days)
   - Find all variant directories in /variants/
   - Check if source image exists and is used on site
   - Delete unused variants (not found in posts)
   - Log: "Cleaned up {$count} orphaned variants, freed {$bytes} bytes"
   - Returns: cleanup report array

5. get_orphaned_images()
   - Search /variants/ for image IDs without corresponding uploads
   - Check if variants are referenced in any posts
   - Returns: array of orphaned image IDs

6. estimate_storage_usage()
   - Calculate total variant storage size
   - Breakdown by format (AVIF, WebP, JPEG)
   - Breakdown by status (used, orphaned)
   - Returns: array with storage statistics

7. audit_log($action, $image_id, $details)
   - Log variant operations to wp_options (serialized array)
   - Keep last 1000 entries (rotate older)
   - Format: timestamp | action | image_id | details
   - Returns: none

8. get_audit_log($limit = 100)
   - Retrieve recent audit log entries
   - Used for admin dashboard display
   - Returns: array of log entries

9. regenerate_variants_for_image($image_id)
   - Force regenerate all variants for specific image
   - Delete existing variants first
   - Queue generation or execute immediately
   - Log: "Manually regenerated variants for image #{$id}"
   - Returns: variant paths array

10. delete_all_variants()
    - Admin function: bulk delete all variants
    - Used for storage cleanup or optimization strategy change
    - Confirmation required (safety check)
    - Log: "Bulk deleted all {$count} variants, freed {$bytes} bytes"
    - Returns: deletion report
```

**Integration Points**:
- Hooks into add_attachment (upload), delete_attachment (deletion)
- Scheduled via WP-Cron (weekly cleanup)
- Accessed from admin dashboard
- Uses Action Scheduler for background processing

---

#### 2.3 Enhanced Image_Optimizer Integration
**File**: `includes/public/class-image-optimizer.php` (MODIFIED)

**Changes**:
```php
// In process_inline_assets() method:

// NEW: Initialize format optimizer
$format_optimizer = new Image_Format_Optimizer();

// EXISTING: Process lazy loading, width/height, etc.
// ... existing code ...

// NEW: Check if format optimization enabled
if ($this->get_option('enable_image_optimization') && 
    $this->get_option('enable_image_format_conversion')) {
    
    // Process each image tag
    foreach ($dom_images as $img) {
        $src = $img->getAttribute('src');
        
        if ($format_optimizer->should_optimize_image($src)) {
            // Get browser format capability
            $format = $format_optimizer->detect_browser_format();
            
            // Get or generate variant
            $variant_url = $format_optimizer->get_variant_from_cache(
                $image_id, 
                $format
            );
            
            if (!$variant_url && $format !== 'jpeg') {
                // Queue background generation if not cached
                $format_optimizer->queue_background_generation(
                    $image_id, 
                    $src
                );
                // Continue with original (variants will be used next time)
            } else if ($variant_url) {
                // Replace with <picture> tag for format delivery
                $picture_html = $format_optimizer->render_picture_tag(
                    $src,
                    $img->getAttribute('alt'),
                    $img->getAttribute('class')
                );
                // Replace img tag with picture tag
            }
        }
    }
}

// Existing: flush output buffer
```

---

### 3. Implementation Timeline

#### Day 1-2: Image_Format_Optimizer (450 lines)
- [ ] Create class structure with 10 public methods
- [ ] Implement `detect_browser_format()` with Accept header parsing
- [ ] Implement `should_optimize_image()` with dimension checks
- [ ] Implement `generate_avif_variant()` with error handling
- [ ] Implement `generate_webp_variant()` with error handling
- [ ] Implement `render_picture_tag()` with srcset support
- [ ] Add transient-based caching (1 week TTL)
- [ ] Unit tests for format detection and generation
- [ ] **Deliverable**: Format optimizer ready for integration

#### Day 3-4: Image_Variant_Lifecycle_Manager (350 lines)
- [ ] Create class structure with 10 public methods
- [ ] Implement `on_image_upload()` with background queue
- [ ] Implement `on_image_delete()` with variant cleanup
- [ ] Implement `cleanup_orphaned_variants()` as WP-Cron callback
- [ ] Implement `audit_log()` and `get_audit_log()` methods
- [ ] Add WP-Cron scheduling in Activator class
- [ ] Unit tests for lifecycle management
- [ ] **Deliverable**: Lifecycle manager ready for integration

#### Day 5: Integration & Testing
- [ ] Update `Image_Optimizer::process_inline_assets()` to use format optimizer
- [ ] Update `Config::get_field_config()` with new settings:
  - `enable_image_format_conversion` - Toggle AVIF/WebP
  - `avif_quality` - Slider (75-95, default 85)
  - `webp_quality` - Slider (75-95, default 85)
  - `cleanup_orphans_weekly` - Toggle WP-Cron cleanup
- [ ] Update `Activator::activate()` with new defaults
- [ ] Test on staging site with various image types
- [ ] Run PSI report (validate -92 KiB target)
- [ ] **Deliverable**: Phase 2 complete and validated

#### Days 6-7: Admin Dashboard & Reporting (OPTIONAL PHASE 2.5)
- [ ] Create admin dashboard showing:
  - Total variants stored (count + size)
  - Storage breakdown (AVIF vs WebP vs orphaned)
  - Recent operations log
  - Format distribution stats
- [ ] Add bulk action buttons:
  - "Regenerate All Variants"
  - "Delete Unused Variants"
  - "View Audit Log"
- [ ] Create variant management UI
- [ ] **Deliverable**: Admin tools for monitoring

---

## 4. Technical Specifications

### File Storage Structure
```
/wp-content/uploads/coreboost-variants/
├── [image-id]/
│   ├── avif/
│   │   ├── image-name.avif
│   │   └── image-name@2x.avif
│   ├── webp/
│   │   ├── image-name.webp
│   │   └── image-name@2x.webp
│   └── metadata.json
│       └── {original_width, original_height, format, generated_date}
```

### Database Storage
- **wp_options**: Audit log (serialized array, last 1000 entries)
- **wp_postmeta**: Image metadata (dimensions, format, variant status)
- **wp_options**: WP-Cron scheduled event for weekly cleanup

### Caching Strategy
- **Transients**: Variant URLs (1 week TTL)
- **File cache**: Variant existence checks (filesystem)
- **Metadata cache**: Image dimensions (wp_postmeta)

### Background Processing
- **Tool**: Action Scheduler (or WP-Cron fallback)
- **Queue**: Variant generation jobs
- **Batch size**: 5 images per background task
- **Priority**: Low (non-blocking)
- **Timeout**: 30 seconds per batch

---

## 5. Configuration & Settings

### New Admin Settings (Image Optimization Tab)

#### Format Conversion Settings
```php
[
    'enable_image_format_conversion' => [
        'type' => 'checkbox',
        'label' => 'Enable Modern Format Delivery',
        'description' => 'Convert JPEG images to AVIF (primary) and WebP (fallback) for optimal compression',
        'default' => false,
    ],
    'avif_quality' => [
        'type' => 'slider',
        'label' => 'AVIF Quality',
        'description' => 'AVIF compression quality (75-95). Higher = better quality but larger files',
        'min' => 75,
        'max' => 95,
        'default' => 85,
        'step' => 1,
    ],
    'webp_quality' => [
        'type' => 'slider',
        'label' => 'WebP Quality',
        'description' => 'WebP compression quality (75-95). Higher = better quality but larger files',
        'min' => 75,
        'max' => 95,
        'default' => 85,
        'step' => 1,
    ],
    'cleanup_orphans_weekly' => [
        'type' => 'checkbox',
        'label' => 'Weekly Cleanup',
        'description' => 'Automatically delete variants of images no longer used on site (runs weekly via WP-Cron)',
        'default' => true,
    ],
    'image_generation_mode' => [
        'type' => 'select',
        'label' => 'Variant Generation',
        'description' => 'on-demand: Generate on first request (slower first view, cached after). pre-generate: Generate immediately (slower uploads, faster first view)',
        'options' => [
            'on-demand' => 'On-Demand (Recommended)',
            'eager' => 'Pre-Generate on Upload',
        ],
        'default' => 'on-demand',
    ],
]
```

### Environment Requirements
- **PHP**: 7.4+ (for null-safe operator in error handling)
- **Image Library**: GD Library (bundled) or ImageMagick
- **Disk Space**: Estimated 50-100 MB for variants (varies by image count)
- **WordPress**: 5.0+ (for Action Scheduler support)
- **Memory**: Increased PHP memory_limit may be needed (256MB → 512MB)

---

## 6. Success Criteria & Validation

### Performance Metrics
- [ ] PSI Image Download Time: -92 KiB reduction (from 617 KiB baseline)
- [ ] Page Load Time: <1% increase from variant processing
- [ ] AVIF Compression: 20-30% better than original JPEG
- [ ] WebP Compression: 15-25% better than original JPEG
- [ ] Cache Hit Rate: >95% for subsequent page views

### Functionality Checklist
- [ ] AVIF variants generated on-demand and cached
- [ ] WebP variants generated as fallback
- [ ] <picture> tags render correctly with srcset
- [ ] JPEG fallback works for old browsers
- [ ] Format selection matches Accept header
- [ ] Retina images (@2x) generated and served
- [ ] Variants deleted when image is removed
- [ ] Orphaned variants cleaned weekly
- [ ] Admin dashboard shows storage stats
- [ ] Audit log tracks all operations

### Browser Compatibility Testing
- [ ] Chrome 95+ (AVIF + WebP support)
- [ ] Firefox 93+ (AVIF + WebP support)
- [ ] Safari 16+ (AVIF + WebP support)
- [ ] Safari 13-15 (WebP fallback)
- [ ] Edge 85+ (AVIF + WebP support)
- [ ] IE 11 (JPEG fallback)

### Security Checks
- [ ] Validate image paths (no directory traversal)
- [ ] Sanitize image dimensions (no overflow attacks)
- [ ] Check file permissions on variant storage
- [ ] Verify variant files have correct mime types
- [ ] Add nonce verification to admin actions

---

## 7. Risk Assessment & Mitigation

### Risk 1: Disk Space Overflow
- **Impact**: High (site could run out of storage)
- **Likelihood**: Medium (depending on image volume)
- **Mitigation**: Weekly orphan cleanup, storage limits per image, admin alerts

### Risk 2: Image Processing CPU Spike
- **Impact**: Medium (could slow site during generation)
- **Likelihood**: Medium (on-demand generation)
- **Mitigation**: Background queue processing, batch size limits, non-blocking execution

### Risk 3: Browser Incompatibility
- **Impact**: Low (fallback mechanisms in place)
- **Likelihood**: Low (HTML5 standard)
- **Mitigation**: Comprehensive testing, JPEG fallback, Accept header detection

### Risk 4: Memory Exhaustion
- **Impact**: High (PHP fatal error)
- **Likelihood**: Low (with proper memory limits)
- **Mitigation**: Increase memory_limit, test with large images, monitor memory usage

---

## 8. Future Enhancements (Phase 2.5+)

### Admin Dashboard & Reporting
- Storage usage statistics and trends
- Format distribution analysis
- Performance impact metrics
- Audit log with filtering/search
- Bulk variant management tools

### Advanced Optimization
- Responsive image srcset optimization
- Lossless vs lossy compression modes
- Image resizing for different breakpoints
- Content Delivery Network (CDN) integration
- Lazy loading with intersection observer

### Monitoring & Analytics
- Integration with PSI (PageSpeed Insights) API
- Real-time variant generation statistics
- Storage efficiency reports
- Format adoption metrics by browser
- Performance benchmarking

---

## 9. Deployment Checklist

### Pre-Deployment (Staging)
- [ ] All 10 Image_Format_Optimizer methods implemented and tested
- [ ] All 10 Image_Variant_Lifecycle_Manager methods implemented and tested
- [ ] Integration code added to Image_Optimizer
- [ ] New settings registered in Config and Activator
- [ ] WP-Cron scheduled correctly
- [ ] Admin UI displays new settings
- [ ] Staging site images processed successfully
- [ ] No PHP errors or warnings
- [ ] PSI report shows -92 KiB improvement
- [ ] All browsers tested successfully

### Deployment (Production)
- [ ] Code committed to main branch
- [ ] Database backup before activation
- [ ] Plugin activated on production
- [ ] Monitor error logs for 24 hours
- [ ] Run PSI report on production
- [ ] Verify variant generation working
- [ ] Check disk space usage trends
- [ ] Document deployment in CHANGELOG

### Post-Deployment (Monitoring)
- [ ] Monitor PSI metrics weekly
- [ ] Track storage usage trends
- [ ] Review audit logs monthly
- [ ] Update documentation with real-world stats
- [ ] Gather user feedback
- [ ] Plan Phase 2.5 enhancements

---

## 10. Documentation Requirements

### Code Documentation
- PHPDoc comments for all public methods
- Inline comments for complex logic
- Integration guide for Image_Optimizer
- API documentation for variant management

### User Documentation
- Admin settings guide (what each setting does)
- Troubleshooting guide (common issues)
- Performance tuning guide (quality vs size trade-offs)
- Storage management guide (cleanup strategies)

### Technical Documentation
- Architecture overview (component interactions)
- File structure (variant storage layout)
- Database schema (stored metadata)
- Configuration reference (all settings and defaults)

---

## 11. Budget Summary

### Implementation Cost
- **Image_Format_Optimizer**: 450 lines (~6-8 hours)
- **Image_Variant_Lifecycle_Manager**: 350 lines (~5-7 hours)
- **Integration & Testing**: ~3-4 hours
- **Total Development**: ~15-20 hours

### Resource Requirements
- **Disk Space**: +50-100 MB (variants storage)
- **PHP Memory**: Increase to 512 MB (from typical 256 MB)
- **Bandwidth**: Minimal (variants cached, served from local storage)
- **CPU**: Moderate spike during background generation (mitigated by queuing)

### Expected ROI
- **Performance**: -92 KiB image download reduction
- **User Experience**: Faster page loads (10-15% for image-heavy pages)
- **SEO**: Improved PSI scores, better Core Web Vitals
- **Storage**: Efficient variant cleanup (weekly orphan removal)

---

## Appendix: AVIF vs WebP Detailed Comparison

### AVIF Advantages
- **Compression**: 20-30% better than WebP for photography
- **Quality**: Better perceived quality at same file size
- **Browser Support**: 95% of modern browsers (2023+ devices)
- **Future-Proof**: Latest image codec (AV1 video codec family)

### WebP Advantages
- **Browser Support**: 99% of browsers (vs AVIF's 95%)
- **Maturity**: Established since 2010, very stable
- **Tools**: Better tooling and library support
- **Fallback**: Essential for Safari <16 and older browsers

### Decision Matrix
| Factor | AVIF | WebP | JPEG |
|--------|------|------|------|
| Compression | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| Browser Support | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Generation Speed | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| File Size | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Recommendation** | **PRIMARY** | **FALLBACK** | **ULTIMATE** |

---

## Version History

- **v1.0** (2025-11-28): Initial Phase 2 implementation plan created
  - 3-component architecture defined
  - 450-line Image_Format_Optimizer specified
  - 350-line Image_Variant_Lifecycle_Manager specified
  - AVIF + WebP strategy with JPEG fallback
  - 10-day implementation timeline
  - PSI target: -92 KiB image optimization

---

**Document Status**: Ready for Implementation

**Next Steps**: 
1. Review and approve this plan
2. Begin Phase 2.1 implementation (Image_Format_Optimizer)
3. Proceed through phases sequentially
4. Validate against success criteria before each deployment

---

**Contact**: For questions or clarifications about this implementation plan, refer to the CoreBoost documentation or contact the development team.
