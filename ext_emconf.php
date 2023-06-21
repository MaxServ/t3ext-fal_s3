<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'S3 driver for FAL',
    'description' => 'Connect FAL/TYPO3 to any of the configured S3 buckets with a few clicks. File based configuration allows specific buckets depending on the context of your application.',
    'category' => 'misc',
    'author' => 'Arno Schoon',
    'author_email' => 'support@maxserv.com',
    'author_company' => 'MaxServ B.V.',
    'state' => 'stable',
    'version' => '1.14.2',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-12.5.99',
            'backend' => '10.4.0-12.5.99',
            'extbase' => '10.4.0-12.5.99',
        ],
        'suggests' => [
            'extractor' => '2.0.0-2.99.99'
        ]
    ]
];
