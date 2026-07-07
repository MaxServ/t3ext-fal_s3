<?php

declare(strict_types=1);

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

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Lists the direct children of a directory, backed by a cache entry per directory.
 *
 * The cached entry contains the raw, unfiltered listing. Filtering (excluded
 * folders, name filters) is applied by the caller, so the cache content does
 * not depend on which caller fills it first.
 */
class CachedDirectoryListing
{
    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly \Closure $normalizer
    ) {
    }

    /**
     * @return array
     */
    public function getEntries(string $path, int $iteratorMode): array
    {
        $cacheEntryIdentifier = Cache::buildEntryIdentifier($path, Cache::PREFIX_LISTING);
        // an empty array is a valid cache entry, only a strict false is a miss
        $cachedEntries = $this->cache->get($cacheEntryIdentifier);
        if ($cachedEntries !== false) {
            return $cachedEntries;
        }

        $entries = [];
        $iterator = new \RecursiveDirectoryIterator($path, $iteratorMode);
        /** @var \SplFileInfo $fileOrFolder */
        foreach ($iterator as $fileOrFolder) {
            if ($fileOrFolder->getFilename() === '') {
                continue;
            }
            $entries[] = ($this->normalizer)($fileOrFolder);
        }

        $cacheTags = [Cache::buildEntryIdentifier($path, Cache::TAG_FOLDER_CONTENTS)];
        $this->cache->set($cacheEntryIdentifier, $entries, $cacheTags, 0);

        return $entries;
    }
}
