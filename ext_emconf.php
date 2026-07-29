<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'S3 driver for FAL',
    'description' => 'Connect FAL/TYPO3 to any of the configured S3 buckets with a few clicks. File based configuration allows specific buckets depending on the context of your application.',
    'category' => 'misc',
    'author' => 'Arno Schoon',
    'author_email' => 'support@maxserv.com',
    'author_company' => 'MaxServ B.V.',
    'state' => 'stable',
    'version' => '4.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0 - 14.3.99',
            'backend' => '13.4.0 - 14.3.99',
            'extbase' => '13.4.0 - 14.3.99',
        ],
        'suggests' => [
            'extractor' => '2.6.2-2.99.99'
        ]
    ]
];
