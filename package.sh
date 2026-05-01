#!/bin/bash

# Generates a production-ready ZIP of the plugin.

# Exit immediately if a command exits with a non-zero status
set -e

PLUGIN_SLUG="wp-media-carousel"

# Extract version securely (removes carriage returns and trims spaces)
VERSION=$(grep -i "^ \* Version:" "${PLUGIN_SLUG}.php" | awk -F':' '{print $2}' | xargs | tr -d '\r')

if [ -z "$VERSION" ]; then
    echo "Error: Could not extract version from ${PLUGIN_SLUG}.php"
    exit 1
fi

OUTPUT_DIR="build"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "Packaging ${PLUGIN_SLUG} v${VERSION}..."

# Create a clean build directory
rm -rf "${OUTPUT_DIR}"
mkdir -p "${OUTPUT_DIR}/${PLUGIN_SLUG}"

# Copy files over, explicitly ignoring dev files and folders
rsync -a --progress ./ "${OUTPUT_DIR}/${PLUGIN_SLUG}" \
    --exclude '.git' \
    --exclude '.github' \
    --exclude 'bin' \
    --exclude 'tests' \
    --exclude 'vendor' \
    --exclude 'composer.json' \
    --exclude 'composer.lock' \
    --exclude 'phpunit.xml.dist' \
    --exclude 'package.sh' \
    --exclude "${OUTPUT_DIR}" \
    --exclude '.*' \
    --exclude '*.log' \
    --exclude '*.zip' \
    --exclude 'AGENTS.md'

# Zip it up safely
cd "${OUTPUT_DIR}"
zip -r "${ZIP_NAME}" "${PLUGIN_SLUG}"
# Move back to root and place the zip
cd - > /dev/null
mv "${OUTPUT_DIR}/${ZIP_NAME}" ./

# Cleanup build directory
rm -rf "${OUTPUT_DIR}"

echo "Done! Generated ${ZIP_NAME}"
