# CoreBoost Dev Release Workflow Guide

## Quick Start

### Option 1: GitHub Actions (Recommended - No Local Setup)

**Fastest way to create a dev release:**

1. Go to GitHub: https://github.com/nrdmartinezz/coreboost/actions
2. Click **"Create Dev Release"** workflow
3. Click **"Run workflow"**
4. Select release type (dev, alpha, or beta)
5. Click **"Run workflow"** button
6. Wait ~30 seconds for the release to be created
7. Check the "Releases" tab on GitHub

**That's it!** The workflow:
- ✅ Auto-increments the dev version number
- ✅ Creates a ZIP file with all plugin files
- ✅ Publishes to GitHub as a pre-release
- ✅ Tags it in the repository

---

### Option 2: Local PowerShell Script (Windows)

**For faster local testing before pushing to GitHub:**

```powershell
# Navigate to the plugin directory
cd "d:\natha\Documents\Web Dev Workspace\Code Workspace\CoreBoost\CoreBoost"

# Run the script
.\scripts\dev-release.ps1 -ReleaseType dev

# Options: dev, alpha, beta
.\scripts\dev-release.ps1 -ReleaseType alpha
```

**Output:**
- Creates `coreboost-dev-build-[timestamp]/` directory
- Generates ZIP file with all plugin files
- Creates `RELEASE_INFO.txt` with build details
- Ready to test locally or push to GitHub

---

### Option 3: Local Bash Script (Linux/Mac)

```bash
cd CoreBoost
chmod +x scripts/dev-release.sh
./scripts/dev-release.sh dev

# Options: dev, alpha, beta
./scripts/dev-release.sh alpha
```

---

## Version Scheme

### Dev Releases (Development Builds)
- Format: `2.5.0-dev.1`, `2.5.0-dev.2`, `2.5.0-dev.3`
- Auto-increments each release
- Marked as pre-release on GitHub
- For testing new features

### Alpha Releases
- Format: `2.5.0-alpha.1`, `2.5.0-alpha.2`
- More stable than dev, less tested than beta
- Marked as pre-release on GitHub

### Beta Releases
- Format: `2.5.0-beta.1`, `2.5.0-beta.2`
- Near-final version for broader testing
- Marked as pre-release on GitHub

### Production Releases
- Format: `2.5.0`, `2.5.1`
- Tagged and released separately
- Marked as stable (not pre-release)

---

## Workflow Comparison

| Feature | GitHub Actions | Local Script |
|---------|---|---|
| Speed | ~30 seconds | ~5 seconds |
| Setup | None needed | Already included |
| Auto-increment | ✅ Yes | ✅ Yes |
| GitHub Release | ✅ Auto-published | Manual: `git tag v2.5.0-dev.X && git push origin v2.5.0-dev.X` |
| ZIP Creation | ✅ Included | ✅ Included |
| Testing | Upload to test site | Local file ready |

---

## Common Workflows

### Creating a Dev Release (Typical Flow)

1. Make your changes and commit
2. Open GitHub Actions → "Create Dev Release"
3. Select "dev" as release type
4. Click "Run workflow"
5. Release automatically published in ~30 seconds
6. Download ZIP from Releases tab
7. Test on your site

**No manual tagging. No manual version bumping. Fully automatic.**

---

### Testing Multiple Dev Builds

```powershell
# Build 1: Initial feature test
.\scripts\dev-release.ps1 -ReleaseType dev

# Test...

# Build 2: Bug fixes in the feature
.\scripts\dev-release.ps1 -ReleaseType dev

# Test...

# Build 3: Ready for wider testing
.\scripts\dev-release.ps1 -ReleaseType alpha

# Feedback...

# Build 4: Final pre-release
.\scripts\dev-release.ps1 -ReleaseType beta

# Ready for production release
# - Create stable release (2.5.0)
```

---

## Integration with Your Flow

### Before (Manual Process)
1. Make changes ❌ Slow
2. Manually bump version ❌ Error-prone
3. Create tag manually ❌ Easy to forget
4. Create GitHub release ❌ Bottleneck
5. Upload ZIP ❌ Manual work

**Typical time: 10-15 minutes**

### After (Automated)
1. Make changes ✅ Fast
2. Click GitHub Actions ✅ Instant
3. Select release type ✅ One click
4. Wait 30 seconds ✅ Automatic

**Typical time: 30 seconds + click**

---

## GitHub Actions Details

**File:** `.github/workflows/dev-release.yml`

**Triggers:**
- Manual workflow dispatch (button click)
- Can also be triggered from command line:
  ```bash
  gh workflow run dev-release.yml -f release_type=dev
  ```

**What it does:**
1. Extracts current version from `coreboost.php`
2. Finds latest dev/alpha/beta tag
3. Auto-increments the dev number
4. Creates plugin ZIP
5. Generates release notes
6. Publishes to GitHub with pre-release flag

---

## Troubleshooting

**Q: Version not incrementing correctly?**
- A: GitHub might cache tags. The workflow fetches with `--tags` and `fetch-depth: 0` to ensure fresh data.

**Q: ZIP file structure wrong?**
- A: Check that `includes/` directory exists and has PHP classes. The workflow verifies this.

**Q: Script won't run on Windows?**
- A: Ensure PowerShell execution policy allows scripts:
  ```powershell
  Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
  ```

**Q: Want to automate even more?**
- A: The workflow can be triggered on:
  - Push to a `dev` branch
  - Manual button click (current setup)
  - PR merge to specific branches
  - Scheduled (e.g., daily builds)
  
  Update the `on:` section in `.github/workflows/dev-release.yml`

---

## Next Steps

1. ✅ Workflow is ready to use
2. Test it: Go to GitHub Actions → Click "Create Dev Release"
3. Select "dev" and run
4. Check the Releases tab
5. Download the ZIP and test locally

**Your development bottleneck is solved!** 🎉
