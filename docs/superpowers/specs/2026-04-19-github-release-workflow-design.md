# GitHub Release Workflow Design

## Goal

When a GitHub Release is published via the Releases UI, automatically build the plugin zip and attach it as a release asset.

## Trigger

```yaml
on:
  release:
    types: [published]
```

Fires once per release publication. Does not trigger on push to main or tag creation alone.

## Job: `build-and-upload`

Single job, runs on `ubuntu-latest`, needs `contents: write` permission to upload assets.

### Steps

1. **Checkout** — `actions/checkout@v3`
2. **Run package.sh** — `bash package.sh` produces `wp-media-carousel-{VERSION}.zip` in the repo root
3. **Resolve zip filename** — extract version from `wp-media-carousel.php` using the same `grep` pattern as `package.sh`, construct the expected zip name
4. **Upload asset** — `softprops/action-gh-release@v1` with `files:` set to the resolved zip path; no `tag_name` needed since the workflow runs in the context of the release event

## Changes to Existing File

File: `.github/workflows/release.yml`

| Current (broken) | Updated |
|---|---|
| `on: push: branches: [main]` | `on: release: types: [published]` |
| References `inkiz-media-carousel.php` | References `wp-media-carousel.php` |
| Version logic inconsistent with `package.sh` | Version extracted with same pattern as `package.sh` |

`package.sh` is not modified.

## Out of Scope

- No changes to `package.sh`
- No tag-push trigger
- No auto-increment of version numbers
