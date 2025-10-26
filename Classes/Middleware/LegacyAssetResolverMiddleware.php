<?php

declare(strict_types=1);

namespace Dothome\DhLegacyAssetResolver\Middleware;

use Dothome\DhLegacyAssetResolver\Service\AssetHashResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;

/**
 * TYPO3 Middleware to resolve legacy /typo3conf/ext/ asset paths to _assets paths.
 *
 * This middleware intercepts requests matching the pattern:
 * /typo3conf/ext/{extensionKey}/Resources/Public/{resourcePath}
 *
 * And redirects them to:
 * /_assets/{hash}/{resourcePath}
 *
 * Example:
 * /typo3conf/ext/dh_essentials/Resources/Public/Fonts/fontawesome-pro/fa-brands-400.woff2
 * -> /_assets/edbda043bcf1b01eaac9d4d34415acb8/Fonts/fontawesome-pro/fa-brands-400.woff2
 */
class LegacyAssetResolverMiddleware implements MiddlewareInterface
{
    /**
     * Regex pattern to match legacy asset paths.
     * Captures: extension key and resource path
     */
    private const LEGACY_PATH_PATTERN = '#^/typo3conf/ext/([^/]+)/Resources/Public/(.+)$#';

    /**
     * Use 301 (permanent) or 302 (temporary) redirect.
     * 302 is safer during migration phase, 301 once stable.
     */
    private const REDIRECT_STATUS_CODE = 302;

    public function __construct(
        private readonly AssetHashResolver $assetHashResolver,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Process an incoming server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        // Check if this is a legacy asset path
        if (!$this->isLegacyAssetPath($path)) {
            // Not a legacy path, pass to next middleware
            return $handler->handle($request);
        }

        // Extract extension key and resource path
        $matches = [];
        if (!preg_match(self::LEGACY_PATH_PATTERN, $path, $matches)) {
            // Should not happen due to isLegacyAssetPath check, but be safe
            return $handler->handle($request);
        }

        $extensionKey = $matches[1];
        $resourcePath = $matches[2];

        $this->logger->debug(
            'Legacy asset path detected: {path}',
            [
                'path' => $path,
                'extension' => $extensionKey,
                'resource' => $resourcePath,
            ]
        );

        // Resolve to _assets URL
        $assetsUrl = $this->assetHashResolver->buildAssetUrl($extensionKey, $resourcePath);

        if ($assetsUrl === null) {
            $this->logger->warning(
                'Could not resolve legacy asset path: {path}',
                [
                    'path' => $path,
                    'extension' => $extensionKey,
                ]
            );

            // Return 404 - asset not found
            return $this->createNotFoundResponse($path);
        }

        $this->logger->info(
            'Redirecting legacy asset path: {from} -> {to}',
            [
                'from' => $path,
                'to' => $assetsUrl,
            ]
        );

        // Redirect to _assets URL
        return new RedirectResponse($assetsUrl, self::REDIRECT_STATUS_CODE);
    }

    /**
     * Checks if the given path matches the legacy asset path pattern.
     *
     * @param string $path
     * @return bool
     */
    private function isLegacyAssetPath(string $path): bool
    {
        return (bool)preg_match(self::LEGACY_PATH_PATTERN, $path);
    }

    /**
     * Creates a 404 Not Found response.
     *
     * @param string $path The requested path
     * @return ResponseInterface
     */
    private function createNotFoundResponse(string $path): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(
            sprintf(
                '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>404 - Asset Not Found</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
               padding: 2rem; max-width: 800px; margin: 0 auto; }
        h1 { color: #d9534f; }
        code { background: #f5f5f5; padding: 0.2rem 0.4rem; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>404 - Asset Not Found</h1>
    <p>The requested legacy asset could not be resolved:</p>
    <p><code>%s</code></p>
    <p>This may indicate:</p>
    <ul>
        <li>The extension is not installed or available</li>
        <li>The extension does not have a Resources/Public directory</li>
        <li>The asset file does not exist in the _assets directory</li>
    </ul>
    <hr>
    <p><small>Resolved by: <strong>dh_legacy_asset_resolver</strong> extension</small></p>
</body>
</html>',
                htmlspecialchars($path, ENT_QUOTES, 'UTF-8')
            )
        );

        return $response->withStatus(404, 'Not Found')
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
