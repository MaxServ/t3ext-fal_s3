<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'S3 driver for FAL',
    'description' => 'Connect FAL/TYPO3 to any of the configured S3 buckets with a few clicks. File based configuration allows specific buckets depending on the context of your application.',
    'category' => 'misc',
    'author' => 'Arno Schoon',
    'author_email' => 'arno@maxserv.com',
    'author_company' => 'MaxServ B.V.',
    'state' => 'beta',
    'clearCacheOnLoad' => 0,
    'version' => '1.8.3',
    '_md5_values_when_last_written' => 'a:0:{}',
    'constraints' => array(
        'depends' => array(
            'typo3' => '6.2.12-9.5.99',
        ),
        'conflicts' => array(
        ),
        'suggests' => array(
        ),
    ),
);
