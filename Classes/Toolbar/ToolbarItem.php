<?php

namespace MaxServ\FalS3\Toolbar;

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Toolbar\ClearCacheActionsHookInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ToolbarItem
 * @package MaxServ\FalS3\Toolbar
 */
class ToolbarItem implements ClearCacheActionsHookInterface
{
    const ITEM_KEY = 'flushFalS3Cache';
    const ITEM_ICON_IDENTIFIER = 'tx_fal_s3_flushcache';

    /**
     * Adds the flush Fal S3 cache menu item
     *
     * @param array $cacheActions Array of CacheMenuItems
     * @param array $optionValues Array of AccessConfigurations-identifiers (typically used by userTS with options.clearCache.identifier)
     */
    public function manipulateCacheActions(&$cacheActions, &$optionValues)
    {
        // First check if user has right to access the flush language cache item
        $tsConfig = $this->getBackendUser()->getTSConfig();
        $option = (bool)$tsConfig['options.']['clearCache.'][self::ITEM_KEY];
        if ($option || $this->getBackendUser()->isAdmin()) {
            /** @var UriBuilder $uriBuilder */
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            try {
                $cacheActions[] = [
                    'id' => self::ITEM_KEY,
                    'title' => 'LLL:EXT:fal_s3/Resources/Private/Language/locallang.xlf:flushFalS3Cache',
                    'description' => 'LLL:EXT:fal_s3/Resources/Private/Language/locallang.xlf:flushFalS3Cache.description',
                    'href' => $uriBuilder->buildUriFromRoute(self::ITEM_KEY),
                    'iconIdentifier' => self::ITEM_ICON_IDENTIFIER
                ];
                $optionValues[] = self::ITEM_KEY;
            } catch (RouteNotFoundException $e) {
                // Do nothing, i.e. do not add the menu item if the AJAX route cannot be found
            }
        }
    }

    /**
     * Wrapper around the global BE user object.
     *
     * @return BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }
}
