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

use Aws;
use TYPO3\CMS\Core;

/**
 * Class Cache
 *
 * The runtime LRU cache is used as first line of defense,
 * to prevent excessive S3 API calls a simple file is stored
 * locally, using some kind of memory based backend could
 * improve performance even more.
 *
 * @package MaxServ\FalS3\Driver
 */
class Cache extends Aws\LruArrayCache
{

    /**
     * @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
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
     *
     * @return void
     */
    public function set($key, $value, $ttl = 0)
    {
        $key = rtrim($key, '/');
        $cacheFrontend = self::getCacheFrontend();
        $entryIdentifier = self::buildEntryIdentifier($key);

        parent::set($key, $value, $ttl);

        $cacheFrontend->set($entryIdentifier, $value, array(), $ttl);
    }

    /**
     * Remove a cache key.
     *
     * @param string $key Key to remove.
     *
     * @return void
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
     * @return \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
     */
    public static function getCacheFrontend()
    {
        $cacheManager = Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager');

        if (self::$cacheFrontend === null
            && is_array($GLOBALS['TYPO3_CONF_VARS']) && array_key_exists('SYS', $GLOBALS['TYPO3_CONF_VARS'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']) && array_key_exists('caching', $GLOBALS['TYPO3_CONF_VARS']['SYS'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching'])
            && array_key_exists('cacheConfigurations', $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'])
            && array_key_exists('tx_fal_s3', $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'])
            && $cacheManager instanceof Core\Cache\CacheManager
        ) {
            $cacheManager->setCacheConfigurations(array(
                'tx_fal_s3' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_fal_s3']
            ));
        }

        if ($cacheManager instanceof Core\Cache\CacheManager && $cacheManager->hasCache('tx_fal_s3')) {
            self::$cacheFrontend = $cacheManager->getCache('tx_fal_s3');
        }

        return self::$cacheFrontend;
    }
}
