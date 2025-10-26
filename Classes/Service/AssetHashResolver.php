<?php

declare(strict_types=1);

namespace Dothome\DhLegacyAssetResolver\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Service to resolve extension keys to their corresponding _assets hash directories.
 *
 * This service scans the web/_assets directory to find symlinks that point to
 * extension Resources/Public directories and caches the results for performance.
 */
class AssetHashResolver
{
    private const CACHE_IDENTIFIER_PREFIX = 'ext_hash_';
    private const CACHE_LIFETIME = 86400; // 24 hours

    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly FrontendInterface $runtimeCache,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Resolves an extension key to its _assets hash directory.
     *
     * @param string $extensionKey The extension key (e.g., 'dh_essentials')
     * @return string|null The hash directory name or null if not found
     */
    public function resolveExtensionHash(string $extensionKey): ?string
    {
        $cacheIdentifier = self::CACHE_IDENTIFIER_PREFIX . $extensionKey;

        // Try to get from cache first
        if ($this->runtimeCache->has($cacheIdentifier)) {
            $cachedHash = $this->runtimeCache->get($cacheIdentifier);
            if ($cachedHash !== false) {
                return $cachedHash;
            }
        }

        // Extension must exist
        if (!$this->packageManager->isPackageAvailable($extensionKey)) {
            $this->logger->debug('Extension not found: {extension}', ['extension' => $extensionKey]);
            $this->runtimeCache->set($cacheIdentifier, false, [], self::CACHE_LIFETIME);
            return null;
        }

        $package = $this->packageManager->getPackage($extensionKey);
        $publicResourcesPath = $package->getPackagePath() . 'Resources/Public';

        // Check if Resources/Public exists
        if (!is_dir($publicResourcesPath)) {
            $this->logger->debug(
                'No Resources/Public directory for extension: {extension}',
                ['extension' => $extensionKey]
            );
            $this->runtimeCache->set($cacheIdentifier, false, [], self::CACHE_LIFETIME);
            return null;
        }

        // Get canonical path for comparison
        $canonicalPublicPath = realpath($publicResourcesPath);
        if ($canonicalPublicPath === false) {
            $this->logger->warning(
                'Could not resolve real path for extension: {extension}',
                ['extension' => $extensionKey, 'path' => $publicResourcesPath]
            );
            $this->runtimeCache->set($cacheIdentifier, false, [], self::CACHE_LIFETIME);
            return null;
        }

        // Scan _assets directory for matching symlink
        $assetsPath = Environment::getPublicPath() . '/_assets';
        if (!is_dir($assetsPath)) {
            $this->logger->error('_assets directory not found at: {path}', ['path' => $assetsPath]);
            return null;
        }

        $hash = $this->findHashForPath($assetsPath, $canonicalPublicPath);

        if ($hash !== null) {
            $this->logger->debug(
                'Resolved extension {extension} to hash {hash}',
                ['extension' => $extensionKey, 'hash' => $hash]
            );
            $this->runtimeCache->set($cacheIdentifier, $hash, [], self::CACHE_LIFETIME);
            return $hash;
        }

        $this->logger->warning(
            'No _assets hash found for extension: {extension}',
            ['extension' => $extensionKey, 'publicPath' => $canonicalPublicPath]
        );
        $this->runtimeCache->set($cacheIdentifier, false, [], self::CACHE_LIFETIME);
        return null;
    }

    /**
     * Scans the _assets directory to find a hash directory that symlinks to the given path.
     *
     * @param string $assetsPath The _assets directory path
     * @param string $targetPath The canonical path to find
     * @return string|null The hash directory name or null
     */
    private function findHashForPath(string $assetsPath, string $targetPath): ?string
    {
        $directories = scandir($assetsPath);
        if ($directories === false) {
            $this->logger->error('Could not scan _assets directory: {path}', ['path' => $assetsPath]);
            return null;
        }

        foreach ($directories as $dir) {
            // Skip . and ..
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $fullPath = $assetsPath . '/' . $dir;

            // Check if it's a symlink
            if (!is_link($fullPath)) {
                continue;
            }

            // Get the symlink target
            $linkTarget = readlink($fullPath);
            if ($linkTarget === false) {
                continue;
            }

            // Resolve to absolute path if relative
            if (!PathUtility::isAbsolutePath($linkTarget)) {
                $linkTarget = realpath($assetsPath . '/' . $linkTarget);
            } else {
                $linkTarget = realpath($linkTarget);
            }

            // Compare paths
            if ($linkTarget === $targetPath) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Builds the full _assets URL for a given extension and resource path.
     *
     * @param string $extensionKey The extension key
     * @param string $resourcePath The resource path (e.g., 'Fonts/roboto.woff2')
     * @return string|null The full _assets URL or null if resolution fails
     */
    public function buildAssetUrl(string $extensionKey, string $resourcePath): ?string
    {
        $hash = $this->resolveExtensionHash($extensionKey);
        if ($hash === null) {
            return null;
        }

        // Ensure resource path doesn't start with slash
        $resourcePath = ltrim($resourcePath, '/');

        return '/_assets/' . $hash . '/' . $resourcePath;
    }

    /**
     * Clears the cache for a specific extension or all extensions.
     *
     * @param string|null $extensionKey Extension key to clear, or null for all
     */
    public function clearCache(?string $extensionKey = null): void
    {
        if ($extensionKey !== null) {
            $cacheIdentifier = self::CACHE_IDENTIFIER_PREFIX . $extensionKey;
            $this->runtimeCache->remove($cacheIdentifier);
            $this->logger->info('Cleared cache for extension: {extension}', ['extension' => $extensionKey]);
        } else {
            $this->runtimeCache->flush();
            $this->logger->info('Cleared all asset hash caches');
        }
    }
}
