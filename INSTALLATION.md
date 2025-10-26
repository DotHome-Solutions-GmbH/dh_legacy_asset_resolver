# Installation Guide

## Quick Start

### 1. Register Extension in Composer

Since this is a local package, add it to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/dh_legacy_asset_resolver"
        }
    ],
    "require": {
        "dothome/dh-legacy-asset-resolver": "@dev"
    }
}
```

### 2. Install via Composer

```bash
ddev composer require dothome/dh-legacy-asset-resolver:@dev
```

### 3. Activate Extension

```bash
# Via TYPO3 CLI
ddev typo3 extension:activate dh_legacy_asset_resolver

# Or via Extension Manager in TYPO3 Backend
# Admin Tools > Extensions > Installed Extensions > Activate
```

### 4. Clear Caches

```bash
ddev typo3 cache:flush
```

### 5. Verify Installation

Test a legacy asset path:

```bash
# Test with curl
curl -I https://your-site.ddev.site/typo3conf/ext/dh_essentials/Resources/Public/test.css

# Expected response:
# HTTP/1.1 302 Found
# Location: /_assets/{hash}/test.css
```

## Manual Installation (Alternative)

If you prefer not to use Composer's path repository:

### 1. Install Dependencies Manually

```bash
cd packages/dh_legacy_asset_resolver
composer install
```

### 2. Register in PackageStates.php

Add to `config/system/settings.php` or activate via Extension Manager:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXT']['extListArray'][] = 'dh_legacy_asset_resolver';
```

### 3. Clear Caches

```bash
ddev typo3 cache:flush
```

## Verification

### Check Middleware Registration

```bash
ddev typo3 list:middleware
```

Look for:
```
frontend  dothome/dh-legacy-asset-resolver  Dothome\DhLegacyAssetResolver\Middleware\LegacyAssetResolverMiddleware
```

### Test with Browser DevTools

1. Open your site in browser
2. Open DevTools (F12) → Network tab
3. Look for requests to `/typo3conf/ext/*/Resources/Public/*`
4. Verify they show:
   - Status: `302 Found`
   - Location header: `/_assets/{hash}/...`

### Check Logs

```bash
# View logs
tail -f var/log/typo3_*.log | grep "DhLegacyAssetResolver"

# Or check specific log file
cat var/log/typo3_legacy_asset_resolver_*.log
```

## Configuration

### Enable Debug Logging

Add to `config/system/additional.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Dothome']['DhLegacyAssetResolver']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFileInfix' => 'legacy_asset_resolver',
        ],
    ],
];
```

### Change Redirect Type

Edit `Classes/Middleware/LegacyAssetResolverMiddleware.php`:

```php
// Line ~35
private const REDIRECT_STATUS_CODE = 301; // Permanent redirect
// or
private const REDIRECT_STATUS_CODE = 302; // Temporary redirect (default)
```

After changing, clear caches:

```bash
ddev typo3 cache:flush
```

## Troubleshooting

### Extension Not Found

**Error:** `Extension dh_legacy_asset_resolver not found`

**Solution:**
```bash
# Check if extension is in packages/
ls -la packages/dh_legacy_asset_resolver/

# Re-run composer
ddev composer install

# Activate manually
ddev typo3 extension:activate dh_legacy_asset_resolver
```

### Middleware Not Registered

**Symptom:** Legacy paths return 404 instead of redirecting

**Solution:**
```bash
# Check middleware stack
ddev typo3 list:middleware | grep legacy

# If not found, clear all caches
ddev typo3 cache:flush
rm -rf var/cache/*

# Restart PHP-FPM
ddev restart
```

### Assets Still Not Loading

**Symptom:** Redirects work but assets still 404

**Solution:**
```bash
# Check _assets directory exists
ls -la web/_assets/ | head -20

# Rebuild symlinks
ddev typo3 cache:flush
ddev typo3 extension:setup

# Check specific extension hash
ls -la web/_assets/ | grep dh_essentials
```

### Redirect Loop

**Symptom:** Browser shows "Too many redirects"

**Cause:** Multiple middleware or .htaccess rules interfering

**Solution:**
```bash
# Check for duplicate rules in .htaccess
grep -n "typo3conf/ext" web/.htaccess

# Check middleware order
ddev typo3 list:middleware | grep -A 5 legacy-asset
```

## Uninstallation

When you no longer need this extension:

```bash
# Deactivate
ddev typo3 extension:deactivate dh_legacy_asset_resolver

# Remove via composer
ddev composer remove dothome/dh-legacy-asset-resolver

# Or manually delete
rm -rf packages/dh_legacy_asset_resolver
```

## Next Steps

After successful installation:

1. Monitor logs for resolution failures
2. Update SCSS files to use relative paths (long-term solution)
3. Test all asset-heavy pages
4. Consider enabling HTTP cache for better performance

See [README.md](README.md) for complete documentation.
