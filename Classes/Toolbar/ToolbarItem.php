<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Toolbar;

use MaxServ\FalS3\Resource\Event\FlushCacheActionEvent;
use TYPO3\CMS\Backend\Toolbar\ClearCacheActionsHookInterface;

/**
 * Class ToolbarItem
 */
class ToolbarItem extends FlushCacheActionEvent implements ClearCacheActionsHookInterface
{
    /**
     * Adds the flush Fal S3 cache menu item
     *
     * @param array $cacheActions Array of CacheMenuItems
     * @param array $optionValues Array of AccessConfigurations-identifiers (typically used by userTS with options.clearCache.identifier)
     */
    public function manipulateCacheActions(&$cacheActions, &$optionValues): void
    {
        // First check if user has right to access the flush language cache item
        if ($this->isCacheItemAvailable()) {
            $cacheActionConfiguration = $this->getCacheActionConfiguration();
            if (!empty($cacheActionConfiguration)) {
                $cacheActions[] = $cacheActionConfiguration;
                $optionValues[] = self::ITEM_KEY;
            }
        }
    }
}
