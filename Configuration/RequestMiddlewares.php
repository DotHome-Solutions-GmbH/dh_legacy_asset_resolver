<?php

/**
 * Request Middleware Stack Configuration
 *
 * This file registers the LegacyAssetResolverMiddleware in TYPO3's middleware stack.
 * The middleware runs early in the request processing, before static route resolution.
 */

return [
    'frontend' => [
        'dothome/dh-legacy-asset-resolver' => [
            'target' => \Dothome\DhLegacyAssetResolver\Middleware\LegacyAssetResolverMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
                'typo3/cms-frontend/static-route-resolver',
            ],
        ],
    ],
];
