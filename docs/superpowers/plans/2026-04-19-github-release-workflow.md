# GitHub Release Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite `.github/workflows/release.yml` so publishing a GitHub Release triggers a plugin zip build and attaches it as a release asset.

**Architecture:** Replace the push-to-main trigger with `release: published`. Run the existing `package.sh` to produce the zip, extract the version using the same grep pattern as `package.sh`, then upload the zip via `softprops/action-gh-release@v1` in the context of the release event (no `tag_name` needed).

**Tech Stack:** GitHub Actions, `softprops/action-gh-release@v1`, bash

---

### Task 1: Rewrite release.yml

**Files:**
- Modify: `.github/workflows/release.yml`

- [ ] **Step 1: Replace the workflow content**

Replace the entire contents of `.github/workflows/release.yml` with:

```yaml
name: Build and Release

on:
  release:
    types: [published]

jobs:
  build-and-upload:
    runs-on: ubuntu-latest
    permissions:
      contents: write

    steps:
      - name: Checkout Code
        uses: actions/checkout@v3

      - name: Package Plugin
        run: bash package.sh

      - name: Get Version
        id: version
        run: |
          VERSION=$(grep -i "^ \* Version:" wp-media-carousel.php | awk -F':' '{print $2}' | xargs | tr -d '\r')
          echo "zip=wp-media-carousel-${VERSION}.zip" >> $GITHUB_OUTPUT

      - name: Upload Release Asset
        uses: softprops/action-gh-release@v1
        with:
          files: ${{ steps.version.outputs.zip }}
```

- [ ] **Step 2: Verify the file looks correct**

Run:
```bash
cat .github/workflows/release.yml
```

Expected: The file matches the content above exactly — trigger is `release: published`, job is `build-and-upload`, version grep references `wp-media-carousel.php`, no `tag_name` in the upload step.

- [ ] **Step 3: Validate YAML syntax**

Run:
```bash
python3 -c "import yaml, sys; yaml.safe_load(open('.github/workflows/release.yml')); print('YAML valid')"
```

Expected output: `YAML valid`

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/release.yml
git commit -m "ci: trigger release build on GitHub Release publish"
```
