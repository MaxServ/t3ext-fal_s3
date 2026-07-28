<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Tests\Unit\Driver;

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

use MaxServ\FalS3\Driver\AmazonS3Driver;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Class AmazonS3DriverTest
 */
class AmazonS3DriverTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $configuration
     */
    protected function setConfiguration(array $configuration): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'] = $configuration;
    }

    /**
     * @param array<string, string> $configurationKey
     * @param array<string, mixed> $configuration
     * @throws InvalidConfigurationException
     */
    #[DataProvider('processConfigurationDataProvider')]
    public function testProcessConfigurationThrowsErrorOnInvalidConfiguration(
        array $configurationKey,
        array $configuration
    ): void {
        $this->expectException(InvalidConfigurationException::class);
        $this->setConfiguration($configuration);

        /** @var AmazonS3Driver $driver */
        $driver = GeneralUtility::makeInstance(AmazonS3Driver::class, $configurationKey);
        $driver->processConfiguration();
    }

    /**
     * @return array<array{array<string, string>, array<string, mixed>}>
     */
    public static function processConfigurationDataProvider(): array
    {
        return [
            [
                ['configurationKey' => 'content'],
                [
                    'content' => [
                        'bucket' => 'web.maxserv.com.development',
                        'key' => 'S3BUCKETTESTKEY',
                        'secret' => 'S3BUCKETTESTSECRET',
                        'title' => 'Content Storage',
                    ],
                ]
            ],
            [
                ['configurationKey' => 'content'],
                [
                    'content' => [
                        'bucket' => 'web.maxserv.com.development',
                        'secret' => 'S3BUCKETTESTSECRET',
                        'region' => 'eu-west-1',
                        'title' => 'Content Storage',
                    ]
                ]
            ],
            [
                ['configurationKey' => 'content'],
                [
                    'content' => [
                        'bucket' => 'web.maxserv.com.development',
                        'key' => 'S3BUCKETTESTKEY',
                        'region' => 'eu-west-1',
                        'title' => 'Content Storage',
                    ]
                ]
            ],
            [
                ['configurationKey' => 'contentStorage'],
                [
                    'content' => [
                        'bucket' => 'web.maxserv.com.development',
                        'key' => 'S3BUCKETTESTKEY',
                        'secret' => 'S3BUCKETTESTSECRET',
                        'region' => 'eu-west-1',
                        'title' => 'Content Storage',
                    ]
                ]
            ],
            [
                ['configurationKey' => ''],
                []
            ]
        ];
    }

    /**
     * @param array<string, string> $configurationKey
     * @param array<string, mixed> $configuration
     * @throws InvalidConfigurationException
     * @throws InvalidPathException
     */
    #[DataProvider('getPublicUrlDataProvider')]
    public function testGetPublicUrlReturnsCorrectUrlBasedOnSuppliedConfiguration(
        array $configurationKey,
        array $configuration,
        string $fileName,
        string $expected
    ): void {
        $this->setConfiguration($configuration);

        /** @var AmazonS3Driver $driver */
        $driver = GeneralUtility::makeInstance(AmazonS3Driver::class, $configurationKey);
        $driver->processConfiguration();

        $publicUrl = $driver->getPublicUrl($fileName);

        self::assertEquals($expected, $publicUrl);
    }

    /**
     * @return array<array{array<string, string>, array<string, mixed>, string, string}>
     */
    public static function getPublicUrlDataProvider(): array
    {
        return [
            [
                ['configurationKey' => 'content'],
                [
                    'content' => [
                        'basePath' => '',
                        'bucket' => 'web.maxserv.com.development', // s3:// is stripped since method function does this in the FAL S3 driver
                        'key' => 'S3BUCKETTESTKEY',
                        'secret' => 'S3BUCKETTESTSECRET',
                        'region' => 'eu-west-1',
                        'title' => 'Content Storage',
                        'publicBaseUrl' => 'https://d2umlfc4a11nkp.cloudfront.net'
                    ]
                ],
                'user_upload/photo.jpg',
                'https://d2umlfc4a11nkp.cloudfront.net/user_upload/photo.jpg'
            ],
            [
                ['configurationKey' => 'content'],
                [
                    'content' => [
                        'basePath' => 'fileadmin',
                        'bucket' => 'web.maxserv.com.development', // s3:// is stripped since initialize method does this in the FAL S3 driver
                        'key' => 'S3BUCKETTESTKEY',
                        'secret' => 'S3BUCKETTESTSECRET',
                        'region' => 'eu-west-1',
                        'title' => 'Content Storage',
                        'publicBaseUrl' => 'https://d2umlfc4a11nkp.cloudfront.net'
                    ]
                ],
                'user_upload/photo.jpg',
                'https://d2umlfc4a11nkp.cloudfront.net/fileadmin/user_upload/photo.jpg'
            ],
            [
                ['configurationKey' => 'content'],
                [
                    'content' => [
                        'bucket' => 'web.maxserv.com.development', // s3:// is stripped since initialize method does this in the FAL S3 driver
                        'key' => 'S3BUCKETTESTKEY',
                        'secret' => 'S3BUCKETTESTSECRET',
                        'region' => 'eu-west-1',
                        'title' => 'Content Storage',
                    ]
                ],
                'user_upload/photo.jpg',
                'https://web.maxserv.com.development.s3.amazonaws.com/user_upload/photo.jpg'
            ],
            [
                ['configurationKey' => 'content'],
                [
                    'content' => [
                        'basePath' => 'fileadmin',
                        'bucket' => 'web.maxserv.com.development', // s3:// is stripped since initialize method does this in the FAL S3 driver
                        'key' => 'S3BUCKETTESTKEY',
                        'secret' => 'S3BUCKETTESTSECRET',
                        'region' => 'eu-west-1',
                        'title' => 'Content Storage',
                    ]
                ],
                'user_upload/photo.jpg',
                'https://web.maxserv.com.development.s3.amazonaws.com/fileadmin/user_upload/photo.jpg'
            ]
        ];
    }
}
