# Private Repository Authentication Guide

Since CoreBoost is hosted in a private GitHub repository, you need to configure authentication to enable automatic updates.

## Quick Setup (2 minutes)

### Step 1: Create a GitHub Personal Access Token

1. Go to https://github.com/settings/tokens
2. Click "Generate new token (classic)"
3. Give it a descriptive name: "CoreBoost Updates"
4. Select these scopes:
   -  `repo` (full control of private repositories)
   -  `read:user` (read user profile data)
5. Click "Generate token" 
6. **Copy the token** - you'll need it in the next step (it won't show again!)

### Step 2: Add Token to WordPress

Choose ONE of these options:

#### Option A: WordPress Configuration (Recommended)

Edit your `wp-config.php` and add this line:

```php
define('COREBOOST_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxx');
```

Replace `ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxx` with your actual token.

#### Option B: Server Environment Variable

Add to your server environment (contact your hosting provider for help):

```bash
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

For cPanel/WHM:
- Go to Environment Variables in cPanel
- Add `GITHUB_TOKEN` with your token value

For Docker:
- Add to your `.env` file:
  ```
  GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
  ```

### Step 3: Verify It Works

1. Go to WordPress Admin  Dashboard
2. Go to Plugins
3. Wait 30 seconds and refresh the page
4. Look for a "CoreBoost" update notification
5. Check `wp-content/debug.log` for messages like:
   - `CoreBoost: Update checker initialized successfully`
   - `CoreBoost: Version information retrieved from GitHub`

## Troubleshooting

### "Could not determine if updates are available"

**Check 1: Is the token valid?**
```bash
curl -H "Authorization: token YOUR_TOKEN_HERE" https://api.github.com/user
```
Should show your GitHub user info, not an error.

**Check 2: Does token have repo scope?**
- Go to https://github.com/settings/tokens
- Find your token and click on it
- Verify it has the `repo` scope selected

**Check 3: Is token in the right place?**
- For `wp-config.php`: Make sure line is BEFORE `/* That's all, stop editing! */`
- For environment variable: Restart your web server after adding it

**Check 4: Check the logs**
```bash
tail -f wp-content/debug.log | grep CoreBoost
```

### "404 Not Found" errors

This usually means:
- Token doesn't have proper access to the repository
- Repository URL is incorrect (should be `nrdmartinezz/CoreBoost`)
- Repository access was revoked

**Solution:**
- Verify token still works: `curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/nrdmartinezz/CoreBoost`
- If not working, regenerate the token and add it again

### Updates Still Not Showing

1. Clear the update check transient:
   ```bash
   wp transient delete coreboost_update_check
   ```

2. Wait 30 seconds and check Plugins page again

3. If still not working, check `wp-config.php` has the token defined:
   ```php
   var_dump(defined('COREBOOST_GITHUB_TOKEN'));  // Should be true
   var_dump(COREBOOST_GITHUB_TOKEN);  // Should show your token (first 10 chars)
   ```

## Security Best Practices

 **Important Security Notes:**

1. **Never commit your token** to version control
   - Add `wp-config.php` to `.gitignore` if it contains the token
   - Use environment variables in production instead

2. **Use a personal access token, not your GitHub password**
   - Tokens can be revoked without changing your password
   - Tokens can have limited scopes

3. **Rotate tokens regularly**
   - Delete old tokens you don't use
   - Regenerate tokens every 6-12 months
   - If token is exposed, delete it immediately at https://github.com/settings/tokens

4. **Keep token secure**
   - Don't share in emails or chat
   - Don't display in error messages
   - Use environment variables in production

## Alternative: Make Repository Public

If you don't want to manage authentication, you can make the CoreBoost repository public:

1. Go to https://github.com/nrdmartinezz/CoreBoost/settings
2. Scroll to "Danger Zone"
3. Click "Make this repository public"
4. Confirm the action

**Pros:**
- No authentication needed
- Updates work immediately
- Simpler setup

**Cons:**
- Repository code is publicly visible
- Anyone can download/fork your code

## Still Having Issues?

1. Enable WordPress debug mode in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. Check `wp-content/debug.log` for detailed error messages

3. Search the CoreBoost GitHub issues for similar problems

4. Contact your hosting provider if it's a server configuration issue

---

**Need help?** Check the `UPDATE_SYSTEM.md` file for more details about the update system in general.
