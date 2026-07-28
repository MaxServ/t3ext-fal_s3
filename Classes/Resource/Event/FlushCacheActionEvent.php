<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Resource\Event;

use TYPO3\CMS\Backend\Backend\Event\ModifyClearCacheActionsEvent;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FlushCacheActionEvent
{
    public const ITEM_KEY = 'flushFalS3Cache';
    public const ITEM_ICON_IDENTIFIER = 'tx_fal_s3_flushcache';

    public function addClearCacheActions(ModifyClearCacheActionsEvent $event): void
    {
        if (!$this->isCacheItemAvailable()) {
            return;
        }
        $cacheActionConfiguration = $this->getCacheActionConfiguration();
        if ($cacheActionConfiguration !== []) {
            $event->addCacheAction($cacheActionConfiguration);
            $event->addCacheActionIdentifier(self::ITEM_KEY);
        }
    }

    /**
     * Check if user has right to access the flush cache item
     */
    protected function isCacheItemAvailable(): bool
    {
        return $this->getBackendUser()->isAdmin()
            || ($this->getBackendUser()->getTSConfig()['options.']['clearCache.'][self::ITEM_KEY] ?? false);
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
                'title' => 'fal_s3.messages:flushFalS3Cache',
                'description' => 'fal_s3.messages:flushFalS3Cache.description',
                'endpoint' => (string)$uriBuilder->buildUriFromRoute(self::ITEM_KEY),
                'iconIdentifier' => self::ITEM_ICON_IDENTIFIER
            ];
        } catch (RouteNotFoundException) {
            // Do nothing, i.e. do not add the menu item if the AJAX route cannot be found
            return [];
        }
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
