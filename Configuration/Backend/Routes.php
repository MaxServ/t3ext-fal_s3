<?php

return [
    \MaxServ\FalS3\Toolbar\ToolbarItem::ITEM_KEY => [
        'path' => '/tx_fal_s3_flushcache/clear',
        'target' => \MaxServ\FalS3\Controller\FlushCacheController::class . '::flushCache'
    ]
];
