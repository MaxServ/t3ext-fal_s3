<?php

namespace MaxServ\FalS3\Driver;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Aws\LruArrayCache;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Cache
 *
 * The runtime LRU cache is used as first line of defense,
 * to prevent excessive S3 API calls a simple file is stored
 * locally, using some kind of memory based backend could
 * improve performance even more.
 */
class Cache extends LruArrayCache
{
    /**
     * @var VariableFrontend
     */
    protected static $cacheFrontend;

    /**
     * Get a cache item by key.
     *
     * @param string $key Key to retrieve.
     *
     * @return mixed|null Returns the value or null if not found.
     */
    public function get($key)
    {
        $key = rtrim($key, '/');
        $cacheEntry = parent::get($key);
        if ($cacheEntry) {
            return $cacheEntry;
        }

        $cacheFrontend = self::getCacheFrontend();
        $entryIdentifier = self::buildEntryIdentifier($key);

        return $cacheFrontend->get($entryIdentifier);
    }

    /**
     * Set a cache key value.
     *
     * @param string $key Key to set
     * @param mixed $value Value to set.
     * @param int $ttl In seconds, 0 = unlimited
     */
    public function set($key, $value, $ttl = 0)
    {
        $key = rtrim($key, '/');
        $cacheFrontend = self::getCacheFrontend();
        $entryIdentifier = self::buildEntryIdentifier($key);

        parent::set($key, $value, $ttl);

        $cacheFrontend->set($entryIdentifier, $value, [], $ttl);
    }

    /**
     * Remove a cache key.
     *
     * @param string $key Key to remove.
     */
    public function remove($key)
    {
        $key = rtrim($key, '/');
        $cacheFrontend = self::getCacheFrontend();
        $entryIdentifier = self::buildEntryIdentifier($key);

        parent::remove($key);

        $cacheFrontend->remove($entryIdentifier);
    }

    /**
     * @param string $key
     * @param string $prefix
     *
     * @return string
     */
    public static function buildEntryIdentifier($key, $prefix = 'fi')
    {
        return $prefix . '-' . md5($key);
    }

    /**
     * @return VariableFrontend
     */
    public static function getCacheFrontend()
    {
        if (
            self::$cacheFrontend === null
            && !empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_fal_s3'])
        ) {
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
            self::$cacheFrontend = $cacheManager->getCache('tx_fal_s3');
        }
        return self::$cacheFrontend;
    }
}
