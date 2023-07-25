<?php

return [
    'frontend' => [
        'middleware-identifier' => [
            'target' => \MaxServ\FalS3\Middleware\FalS3PreconnectMiddleware::class,
            'before' => [
                'typo3/cms-frontend/content-length-headers',
            ],
            'after' => [
                'typo3/cms-frontend/tsfe',
            ],
        ],
    ],
];