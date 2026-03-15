#!/bin/bash

# Generates a production-ready ZIP of the plugin.

PLUGIN_SLUG="inkiz-media-carousel"
VERSION=$(grep -i "Version:" inkiz-media-carousel.php | awk -F' ' '{print $NF}' | tr -d '\r')
OUTPUT_DIR="build"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "Packaging ${PLUGIN_SLUG} v${VERSION}..."

# Create a clean build directory
rm -rf ${OUTPUT_DIR}
mkdir -p ${OUTPUT_DIR}/${PLUGIN_SLUG}

# Copy files over, explicitly ignoring dev files and folders
rsync -av --progress ./ ${OUTPUT_DIR}/${PLUGIN_SLUG} \
    --exclude '.git' \
    --exclude '.github' \
    --exclude 'bin' \
    --exclude 'tests' \
    --exclude 'vendor' \
    --exclude 'composer.json' \
    --exclude 'composer.lock' \
    --exclude 'phpunit.xml.dist' \
    --exclude 'package.sh' \
    --exclude 'build' \
    --exclude '.*' \
    --exclude '*.log'

# Zip it up
cd ${OUTPUT_DIR}
zip -r ${ZIP_NAME} ${PLUGIN_SLUG}
mv ${ZIP_NAME} ../
cd ..

# Cleanup build directory
rm -rf ${OUTPUT_DIR}

echo "Done! Generated ${ZIP_NAME}"
