<?php

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
use Nimut\TestingFramework\TestCase\UnitTestCase;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AmazonS3DriverTest
 * @package MaxServ\FalS3\Tests\Unit\Driver
 */
class AmazonS3DriverTest extends UnitTestCase
{
    /**
     * @var array
     */
    protected $configuration;

    /**
     * Setup for tests
     */
    protected function setUp()
    {
        parent::setUp();

        $this->setConfiguration();
    }

    /**
     * Set configuration for tests
     */
    protected function setConfiguration()
    {
        $this->configuration['content'] = [
            'basePath' => '',
            'bucket' => 's3://web.maxserv.com.development',
            'key' => 'S3BUCKETTESTKEY',
            'secret' => 'S3BUCKETTESTSECRET',
            'region' => 'eu-west-1',
            'title' => 'Content Storage'
        ];

        $this->configuration['stream_protocol'] = 's3.' . GeneralUtility::shortMD5(
            AmazonS3Driver::DRIVER_KEY . '.' . $this->configuration['configurationKey']
        );
    }

    /**
     * @param array $config
     *
     * @dataProvider processConfigurationDataProvider
     */
    public function testProcessConfigurationThrowsErrorOnInvalidConfiguration(array $config)
    {
        $this->expectException(InvalidConfigurationException::class);

        /** @var AmazonS3Driver $driver */
        $driver = GeneralUtility::makeInstance(AmazonS3Driver::class, [$config]);
        $driver->processConfiguration();
    }

    /**
     * @return array
     */
    public function processConfigurationDataProvider()
    {
        return [
            [
                'content' => [
                    'bucket' => 's3://web.maxserv.com.development',
                    'key' => 'S3BUCKETTESTKEY',
                    'secret' => 'S3BUCKETTESTSECRET',
                    'title' => 'Content Storage',
                ],
            ],
            [
                'content' => [
                    'bucket' => 's3://web.maxserv.com.development',
                    'secret' => 'S3BUCKETTESTSECRET',
                    'region' => 'eu-west-1',
                    'title' => 'Content Storage',
                ]
            ],
            [
                'content' => [
                    'bucket' => 's3://web.maxserv.com.development',
                    'key' => 'S3BUCKETTESTKEY',
                    'region' => 'eu-west-1',
                    'title' => 'Content Storage',
                ]
            ],
            [
                []
            ]
        ];
    }
}