<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Driver;

use Aws\LruArrayCache;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Cache extends LruArrayCache
{
    /**
     * Entry prefixes: stat information of a file or folder, the direct
     * children of a folder, a complete folder subtree, the contents
     * of an online media file and the file/folder counts of a folder.
     */
    public const PREFIX_STAT = 'stat';
    public const PREFIX_LISTING = 'listing';
    public const PREFIX_RECURSIVE_LISTING = 'listing-recursive';
    public const PREFIX_FILE_CONTENTS = 'file-contents';
    public const PREFIX_FILE_COUNT = 'count-files';
    public const PREFIX_RECURSIVE_FILE_COUNT = 'count-files-recursive';
    public const PREFIX_FOLDER_COUNT = 'count-folders';
    public const PREFIX_RECURSIVE_FOLDER_COUNT = 'count-folders-recursive';

    /**
     * Tag prefixes: entries depending on the direct contents of a folder
     * and entries depending on the complete subtree below a folder.
     */
    public const TAG_FOLDER_CONTENTS = 'folder-contents';
    public const TAG_FOLDER_SUBTREE = 'folder-subtree';

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
     * @throws NoSuchCacheException
     */
    public function get($key)
    {
        $key = rtrim($key, '/');
        $cacheEntry = parent::get($key);
        if ($cacheEntry !== null) {
            return $cacheEntry;
        }

        $cacheFrontend = self::getCacheFrontend();
        $entryIdentifier = self::buildEntryIdentifier($key);

        $cacheEntry = $cacheFrontend->get($entryIdentifier);
        if ($cacheEntry === false) {
            return null;
        }

        // keep entries fetched from the shared cache in the runtime LRU cache,
        // the AWS StreamWrapper consults this cache on every stat call
        parent::set($key, $cacheEntry);

        return $cacheEntry;
    }

    /**
     * Set a cache key value.
     *
     * @param string $key Key to set
     * @param mixed $value Value to set.
     * @param int $ttl In seconds, 0 = unlimited
     * @throws NoSuchCacheException
     */
    public function set($key, $value, $ttl = 0): void
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
     * @throws NoSuchCacheException
     */
    public function remove($key): void
    {
        $key = rtrim($key, '/');
        $cacheFrontend = self::getCacheFrontend();
        $entryIdentifier = self::buildEntryIdentifier($key);

        parent::remove($key);

        $cacheFrontend->remove($entryIdentifier);
    }

    public static function buildEntryIdentifier(string $key, string $prefix = self::PREFIX_STAT): string
    {
        return $prefix . '-' . md5($key);
    }

    /**
     * @throws NoSuchCacheException
     */
    public static function getCacheFrontend(): VariableFrontend
    {
        if (empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_fal_s3'])) {
            throw new NoSuchCacheException('Missing cache configuration for tx_fal_s3 extension', 1655459397);
        }

        if (self::$cacheFrontend === null) {
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
            self::$cacheFrontend = $cacheManager->getCache('tx_fal_s3');
        }
        return self::$cacheFrontend;
    }
}
