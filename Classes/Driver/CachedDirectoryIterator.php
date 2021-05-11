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

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Class CachedDirectoryIterator
 * @package MaxServ\FalS3\Driver
 */
class CachedDirectoryIterator implements \RecursiveIterator, \SeekableIterator
{
    /**
     * @var
     */
    private $path;

    /**
     * @var
     */
    private $iteratorMode;

    /**
     * @var FrontendInterface
     */
    private $cache;

    /**
     * @var callable
     */
    private $normalizer;

    /**
     * @var callable
     */
    private $filter;

    /**
     * @var int
     */
    private $currentIndex = 0;

    /**
     * @var array
     */
    private $filesAndFolders = [];

    /**
     * CachedDirectoryIterator constructor.
     * @param $path
     * @param $iteratorMode
     * @param FrontendInterface $cache
     * @param callable $normalizer
     * @param callable $filter
     */
    public function __construct(
        $path,
        $iteratorMode,
        FrontendInterface $cache,
        callable $normalizer,
        callable $filter
    ) {
        $this->path = $path;
        $this->iteratorMode = $iteratorMode;
        $this->cache = $cache;
        $this->normalizer = $normalizer;
        $this->filter = $filter;
        $this->initialize();
    }

    /**
     * Initialize CachedDirectoryIterator
     */
    private function initialize()
    {
        $cacheEntryIdentifier = Cache::buildEntryIdentifier(
            $this->path,
            'ls'
        );
        $this->filesAndFolders = $this->cache->get($cacheEntryIdentifier);
        if (!$this->filesAndFolders) {
            $this->filesAndFolders = [];
            $iterator = new \RecursiveDirectoryIterator($this->path, $this->iteratorMode);
            /** @var \SplFileInfo $fileOrFolder */
            foreach ($iterator as $fileOrFolder) {
                if (call_user_func($this->filter, $fileOrFolder)) {
                    continue;
                }
                $this->filesAndFolders[] = call_user_func($this->normalizer, $fileOrFolder);
            }
            $cacheTags = [Cache::buildEntryIdentifier($this->path, 'd')];
            $this->cache->set($cacheEntryIdentifier, $this->filesAndFolders, $cacheTags, 0);
        }
    }

    /**
     * @param int $position
     * @see \SeekableIterator::seek()
     */
    public function seek($position)
    {
        $this->currentIndex = $position;
    }

    /**
     * @see Iterator::valid()
     */
    public function valid()
    {
        return isset($this->filesAndFolders[$this->currentIndex]);
    }

    /**
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->currentIndex = 0;
    }

    /**
     * @see Iterator::next()
     */
    public function next()
    {
        $this->currentIndex++;
    }

    /**
     * @see \RecursiveIterator::hasChildren()
     */
    public function hasChildren()
    {
        return substr($this->filesAndFolders[$this->currentIndex], -1) === '/';
    }

    /**
     * @see \RecursiveIterator::getChildren()
     */
    public function getChildren()
    {
        return new self(
            $this->path . basename($this->filesAndFolders[$this->currentIndex]) . '/',
            $this->iteratorMode,
            $this->cache,
            $this->normalizer,
            $this->filter
        );
    }

    /**
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->currentIndex;
    }

    /**
     * @see Iterator::current()
     */
    public function current()
    {
        return $this->filesAndFolders[$this->currentIndex];
    }
}
