<?php
namespace MaxServ\FalS3\MetaData;

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

use Aws;
use MaxServ\FalS3;
use TYPO3;

/**
 * Class RemoteResourceResponseHeaderUpdater
 *
 * @package MaxServ\FalS3\MetaData
 */
class RemoteResourceResponseHeaderUpdater
{

    /**
     * Update the dimension of an image directly after creation
     *
     * @param array $data
     *
     * @return void
     */
    public function recordUpdatedOrCreated(array $data)
    {
        $bucket = null;
        $client = null;

        $file = TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getFileObject($data['file']);

        if ($file instanceof TYPO3\CMS\Core\Resource\File
            && $file->getStorage()->getDriverType() === FalS3\Driver\AmazonS3Driver::DRIVER_KEY
        ) {
            $driverConfiguration = $this->getDriverConfiguration($file->getStorage());

            $bucket = $driverConfiguration['bucket'];

            // strip the s3 protocol prefix from the bucket name
            if (strpos($driverConfiguration['bucket'], 's3://') === 0) {
                $bucket = substr($driverConfiguration['bucket'], 5);
            }

            $client = new Aws\S3\S3Client(array(
                'version' => '2006-03-01',
                'region' => $driverConfiguration['region'],
                'credentials' => array(
                    'key' => $driverConfiguration['key'],
                    'secret' => $driverConfiguration['secret']
                )
            ));
        }

        if ($client instanceof Aws\S3\S3Client) {
            $this->updateResourceMetadata($client, $file, $bucket);

            $processedFileRepository = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                TYPO3\CMS\Core\Resource\ProcessedFileRepository::class
            );

            if ($processedFileRepository instanceof TYPO3\CMS\Core\Resource\ProcessedFileRepository) {
                $processedFiles = $processedFileRepository->findAllByOriginalFile($file);

                array_walk(
                    $processedFiles,
                    function (TYPO3\CMS\Core\Resource\FileInterface $processedFile) use ($client, $bucket) {
                        $this->updateResourceMetadata($client, $processedFile, $bucket);
                    }
                );
            }
        }
    }

    /**
     * @param Aws\S3\S3Client $client
     * @param TYPO3\CMS\Core\Resource\FileInterface $file
     * @param string $bucket
     * @param array $metadata
     *
     * @return void
     */
    protected function updateResourceMetadata(
        Aws\S3\S3Client $client,
        TYPO3\CMS\Core\Resource\FileInterface $file,
        $bucket,
        array $metadata = array()
    ) {
        $key = ltrim($file->getIdentifier(), '/');

        $currentResource = $client->headObject(array(
            'Bucket' => $bucket,
            'Key' => $key
        ));

        if ($currentResource instanceof Aws\Result
            && $currentResource->hasKey('Metadata')
            && is_array($currentResource->get('Metadata'))
        ) {
            $metadata = array_merge(
                $currentResource->get('Metadata'),
                $metadata
            );
        }

        $client->copyObject(array(
            'Bucket' => $bucket,
            'CacheControl' => 'max-age=30',
            'ContentType' => $currentResource->get('ContentType'),
            'CopySource' => $bucket . '/' . $key,
            'Key' => $key,
            'Metadata' => $metadata,
            'MetadataDirective' => 'REPLACE'
        ));
    }

    /**
     * @param TYPO3\CMS\Core\Resource\ResourceStorage $storage
     *
     * @return array
     */
    protected function getDriverConfiguration(TYPO3\CMS\Core\Resource\ResourceStorage $storage)
    {
        $driverConfiguration = array();
        $storageConfiguration = $storage->getConfiguration();

        if (array_key_exists('configurationKey', $storageConfiguration)) {
            $driverConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$storageConfiguration['configurationKey']];
        }

        return $driverConfiguration;
    }
}