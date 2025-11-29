#!/bin/bash
# Quick Dev Release Script for CoreBoost
# Usage: ./scripts/dev-release.sh [dev|alpha|beta]

set -e

RELEASE_TYPE=${1:-dev}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

echo "🚀 CoreBoost Dev Release Builder"
echo "=================================="
echo ""

# Get current version from plugin file
CURRENT_VERSION=$(grep "Version:" "$PLUGIN_DIR/coreboost.php" | sed 's/.*Version: //' | tr -d ' ')
echo "📦 Current plugin version: $CURRENT_VERSION"
echo "📝 Release type: $RELEASE_TYPE"
echo ""

# Build timestamp
BUILD_TIME=$(date '+%Y%m%d-%H%M%S')
DEV_VERSION="${CURRENT_VERSION}-${RELEASE_TYPE}.$(date +%s)"

echo "🏗️  Building dev release: $DEV_VERSION"
echo ""

# Create release directory
RELEASE_DIR="coreboost-dev-build-$BUILD_TIME"
mkdir -p "$RELEASE_DIR/coreboost"

# Copy plugin files
echo "📋 Copying plugin files..."
cp "$PLUGIN_DIR/coreboost.php" "$RELEASE_DIR/coreboost/"
cp "$PLUGIN_DIR/readme.txt" "$RELEASE_DIR/coreboost/"
cp "$PLUGIN_DIR/README.md" "$RELEASE_DIR/coreboost/"
cp "$PLUGIN_DIR/CHANGELOG.md" "$RELEASE_DIR/coreboost/" 2>/dev/null || true

# Copy directories
cp -r "$PLUGIN_DIR/includes" "$RELEASE_DIR/coreboost/" 2>/dev/null || true
cp -r "$PLUGIN_DIR/assets" "$RELEASE_DIR/coreboost/" 2>/dev/null || true

# Verify structure
CLASS_COUNT=$(find "$RELEASE_DIR/coreboost/includes" -name "*.php" 2>/dev/null | wc -l || echo "0")
echo "✅ Files copied ($CLASS_COUNT PHP classes)"
echo ""

# Create ZIP
echo "📦 Creating ZIP archive..."
cd "$RELEASE_DIR"
zip -r "coreboost-${DEV_VERSION}.zip" coreboost > /dev/null
ZIP_FILE="coreboost-${DEV_VERSION}.zip"
ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)
cd - > /dev/null

echo "✅ ZIP created: $ZIP_FILE ($ZIP_SIZE)"
echo ""

# Create info file
INFO_FILE="$RELEASE_DIR/RELEASE_INFO.txt"
cat > "$INFO_FILE" << EOF
CoreBoost Dev Release Info
==========================

Version: $DEV_VERSION
Release Type: $RELEASE_TYPE
Built: $(date -u '+%Y-%m-%d %H:%M:%S UTC')
Build Time: $BUILD_TIME

ZIP File: $ZIP_FILE
Size: $ZIP_SIZE

---
This is a development build. Test thoroughly before production use.
EOF

echo "📄 Release info:"
cat "$INFO_FILE"
echo ""

echo "✅ Dev release ready!"
echo ""
echo "📁 Location: $PLUGIN_DIR/$RELEASE_DIR/"
echo ""
echo "Next steps:"
echo "1. Extract: unzip $RELEASE_DIR/$ZIP_FILE"
echo "2. Test the plugin"
echo "3. If ready, push with: git tag v$DEV_VERSION && git push origin v$DEV_VERSION"
echo ""
