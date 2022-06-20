<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Controller;

use MaxServ\FalS3\Driver\Cache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Http\Response;

/**
 * Class FlushCacheController
 */
class FlushCacheController
{
    /**
     * Main dispatcher entry method registered as "flushFalS3Cache" end point
     * Flushes the Fal S3 cache.
     *
     * @param ServerRequestInterface $request the current request
     * @return ResponseInterface
     * @throws NoSuchCacheException
     */
    public function flushCache(ServerRequestInterface $request)
    {
        $cacheFrontend = Cache::getCacheFrontend();
        $cacheFrontend->flush();

        return new Response();
    }
}
