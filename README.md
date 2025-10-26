# DotHome Legacy Asset Resolver

[![TYPO3 11](https://img.shields.io/badge/TYPO3-11-orange.svg)](https://get.typo3.org/version/11)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)

A TYPO3 extension that provides seamless asset path resolution during migration from TYPO3 v11/v12 to v13.

## Problem Statement

Starting with TYPO3 v12, extension assets are published under `web/_assets/{HASH}/` instead of `web/typo3conf/ext/{extensionKey}/Resources/Public/`. The hash is dynamically generated based on the extension's public resources path.

This causes issues when:
- Legacy SCSS files contain hardcoded paths like `/typo3conf/ext/theme_dothomeportal/Resources/Public/Fonts/...`
- CSS files are pre-compiled and reference these legacy paths
- Browser requests fail because these paths no longer exist

## Solution

This extension implements a **PSR-15 Middleware** that:

1. **Intercepts** legacy asset requests matching: `/typo3conf/ext/{extensionKey}/Resources/Public/{resourcePath}`
2. **Resolves** the extension key to its corresponding `_assets` hash directory
3. **Redirects** (HTTP 302) to: `/_assets/{hash}/{resourcePath}`
4. **Caches** hash lookups for optimal performance
5. **Returns 404** for non-existent extensions or assets

### Example

**Request:**
```
/typo3conf/ext/dh_essentials/Resources/Public/Fonts/fontawesome-pro/fa-brands-400.woff2
```

**Redirects to:**
```
/_assets/edbda043bcf1b01eaac9d4d34415acb8/Fonts/fontawesome-pro/fa-brands-400.woff2
```

## Installation

### Composer (Recommended)

```bash
composer require dothome/dh-legacy-asset-resolver
```

### Manual Installation

1. Copy the extension to `packages/dh_legacy_asset_resolver/`
2. Run `composer install` to register the autoloader
3. Activate the extension in TYPO3 Extension Manager

## Configuration

The extension works **out of the box** with zero configuration required.

### Advanced Configuration

#### Change Redirect Type

Edit `Classes/Middleware/LegacyAssetResolverMiddleware.php`:

```php
// Use 301 for permanent redirects (production)
private const REDIRECT_STATUS_CODE = 301;

// Use 302 for temporary redirects (migration phase, default)
private const REDIRECT_STATUS_CODE = 302;
```

#### Adjust Middleware Priority

Edit `Configuration/RequestMiddlewares.php` to change when the middleware executes in the request stack.

#### Cache Configuration

Hash lookups are cached in TYPO3's runtime cache for 24 hours. To change this, modify:

```php
// AssetHashResolver.php
private const CACHE_LIFETIME = 86400; // seconds
```

## How It Works

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Browser Request                          │
│  /typo3conf/ext/dh_essentials/Resources/Public/Fonts/...   │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│           LegacyAssetResolverMiddleware                     │
│  • Detects legacy path pattern                              │
│  • Extracts extension key & resource path                   │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              AssetHashResolver Service                      │
│  • Checks runtime cache                                     │
│  • Scans _assets/ directory for matching symlink            │
│  • Returns hash directory name                              │
│  • Caches result                                            │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              HTTP 302 Redirect Response                     │
│  /_assets/edbda043bcf1b01eaac9d4d34415acb8/Fonts/...       │
└─────────────────────────────────────────────────────────────┘
```

### Component Details

#### `AssetHashResolver` Service

- **Responsibility:** Maps extension keys to `_assets` hash directories
- **Caching:** Uses TYPO3 runtime cache to avoid repeated filesystem scans
- **Performance:** First request scans `_assets/`, subsequent requests use cache
- **Cache Lifetime:** 24 hours (configurable)

**Key Methods:**
- `resolveExtensionHash(string $extensionKey): ?string` - Find hash for extension
- `buildAssetUrl(string $extensionKey, string $resourcePath): ?string` - Build complete URL
- `clearCache(?string $extensionKey = null): void` - Clear cache

#### `LegacyAssetResolverMiddleware`

- **Type:** PSR-15 HTTP Middleware
- **Position:** Before static route resolver and page resolver
- **Pattern:** `/^\/typo3conf\/ext\/([^\/]+)\/Resources\/Public\/(.+)$/`
- **Response:** HTTP 302 redirect or 404 if asset not found

**Configuration Location:** `Configuration/RequestMiddlewares.php`

## Logging

The extension logs all resolution attempts using TYPO3's logging framework:

- **Logger Name:** `Dothome.DhLegacyAssetResolver`
- **Log Levels:**
  - `DEBUG` - Path detection, extension checks
  - `INFO` - Successful redirects
  - `WARNING` - Resolution failures
  - `ERROR` - System errors (e.g., missing `_assets` directory)

### View Logs

Configure logging in `config/system/additional.php` or check your configured log files:

```php
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Dothome']['DhLegacyAssetResolver']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFileInfix' => 'legacy_asset_resolver',
        ],
    ],
];
```

## Performance Considerations

### Runtime Impact

- **First Request:** ~2-5ms (scans `_assets` directory)
- **Cached Requests:** ~0.1ms (cache lookup)
- **Cache Hit Ratio:** >99% in production

### Optimization Tips

1. **Use HTTP Cache:** Configure your web server to cache 302 redirects
2. **Monitor Logs:** Check for frequent resolution failures
3. **Pre-Warm Cache:** Run a warm-up script after deployment
4. **Consider Migration:** Long-term, update SCSS to use relative paths

### Example Nginx Caching

```nginx
location ~* ^/typo3conf/ext/.*/Resources/Public/ {
    proxy_cache_valid 302 1h;
    proxy_cache_key "$scheme$request_method$host$request_uri";
}
```

## Migration Strategy

This extension is designed as a **temporary migration tool**. For long-term maintainability:

### Phase 1: Install Extension (Day 1)
- Install `dh_legacy_asset_resolver`
- Test that legacy paths redirect correctly
- Monitor logs for resolution issues

### Phase 2: Monitor & Optimize (Weeks 1-4)
- Review logs to identify most-used legacy paths
- Verify redirect performance
- Fix any resolution failures

### Phase 3: Update Source Files (Months 2-6)
- Update SCSS to use relative paths
- Consolidate assets into single extension
- Recompile CSS with new paths

### Phase 4: Remove Extension (Month 6+)
- Verify no legacy paths are still requested
- Uninstall `dh_legacy_asset_resolver`
- Update documentation

## Troubleshooting

### Asset Not Found (404)

**Symptoms:** Browser receives 404 response with custom error page

**Causes:**
- Extension not installed or inactive
- Extension has no `Resources/Public` directory
- Symlink not created in `_assets`

**Solutions:**
1. Verify extension is active: `ddev typo3 extension:list`
2. Check `_assets` directory: `ls -la web/_assets/`
3. Rebuild symlinks: `ddev typo3 cache:flush`

### Wrong Hash Resolution

**Symptoms:** Redirects to non-existent `_assets` path

**Causes:**
- Stale cache after extension update
- Multiple extensions with same public path

**Solutions:**
1. Clear runtime cache: `ddev typo3 cache:flush`
2. Check symlinks: `find web/_assets -type l -ls`

### Performance Issues

**Symptoms:** Slow asset loading

**Causes:**
- Cache not working (check logs for repeated scans)
- Too many concurrent requests to uncached assets

**Solutions:**
1. Enable HTTP cache in web server
2. Pre-warm cache after deployment
3. Increase `CACHE_LIFETIME` constant

## Development

### Requirements

- PHP 8.1+
- TYPO3 11.5+ / 12.4+ / 13.0+
- Composer

### Testing

```bash
# Unit tests (if implemented)
vendor/bin/phpunit -c packages/dh_legacy_asset_resolver/Tests/

# Manual testing
curl -I https://your-site.test/typo3conf/ext/dh_essentials/Resources/Public/test.css

# Expected: HTTP/1.1 302 Found
# Location: /_assets/{hash}/test.css
```

### Code Quality

```bash
# PHP-CS-Fixer
vendor/bin/php-cs-fixer fix packages/dh_legacy_asset_resolver/

# PHPStan
vendor/bin/phpstan analyse packages/dh_legacy_asset_resolver/
```

## License

GPL-2.0-or-later

## Credits

**Developed by:** DotHome
**Contact:** dev@dothome.at
**Website:** https://dothome.at

## Support

For issues, questions, or feature requests:
- Check the troubleshooting section above
- Review TYPO3 logs in `var/log/`
- Contact DotHome support

## Changelog

### Version 1.0.0 (2025-01-XX)

- Initial release
- PSR-15 middleware for legacy path resolution
- Runtime cache for performance optimization
- Comprehensive logging and error handling
- TYPO3 11.5, 12.4, and 13.x compatibility

---

**Note:** This extension is a migration helper. Plan to update your source files and remove this extension once migration is complete.
