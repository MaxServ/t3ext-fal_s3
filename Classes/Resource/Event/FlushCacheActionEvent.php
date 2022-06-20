<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Resource\Event;

use TYPO3\CMS\Backend\Backend\Event\ModifyClearCacheActionsEvent;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FlushS3CacheEvent
 */
class FlushCacheActionEvent
{
    public const ITEM_KEY = 'flushFalS3Cache';
    public const ITEM_ICON_IDENTIFIER = 'tx_fal_s3_flushcache';

    /**
     * Add the flush Fal S3 cache menu item
     *
     * @param ModifyClearCacheActionsEvent $event
     */
    public function addClearCacheActions(ModifyClearCacheActionsEvent $event): void
    {
        if ($this->isCacheItemAvailable()) {
            $cacheActionConfiguration = $this->getCacheActionConfiguration();
            if (!empty($cacheActionConfiguration)) {
                $event->addCacheAction($cacheActionConfiguration);
                $event->addCacheActionIdentifier(self::ITEM_KEY);
            }
        }
    }

    /**
     * Check if user has right to access the flush cache item
     * @return bool
     */
    protected function isCacheItemAvailable(): bool
    {
        return $this->getBackendUser()->isAdmin()
            || $this->getBackendUser()->getTSConfig()['options.']['clearCache.'][self::ITEM_KEY] ?? false;
    }

    /**
     * @return string[]
     */
    protected function getCacheActionConfiguration(): array
    {
        try {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            return [
                'id' => self::ITEM_KEY,
                'title' => 'LLL:EXT:fal_s3/Resources/Private/Language/locallang.xlf:flushFalS3Cache',
                'description' => 'LLL:EXT:fal_s3/Resources/Private/Language/locallang.xlf:flushFalS3Cache.description',
                'href' => (string)$uriBuilder->buildUriFromRoute(self::ITEM_KEY),
                'iconIdentifier' => self::ITEM_ICON_IDENTIFIER
            ];
        } catch (RouteNotFoundException $e) {
            // Do nothing, i.e. do not add the menu item if the AJAX route cannot be found
            return [];
        }
    }

    /**
     * Returns the current BE user.
     *
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
