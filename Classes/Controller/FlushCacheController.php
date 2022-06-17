<?php

namespace MaxServ\FalS3\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FlushCacheController
 */
class FlushCacheController
{
    /**
     * Main dispatcher entry method registered as "flushLanguageCache" end point
     * Flushes the language cache (l10n).
     *
     * @param ServerRequestInterface $request the current request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function flushCache(ServerRequestInterface $request)
    {
        $cacheFrontend = GeneralUtility::makeInstance(CacheManager::class)->getCache('tx_fal_s3');
        $cacheFrontend->flush();

        return new Response();
    }
}
