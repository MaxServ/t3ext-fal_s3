<?php

namespace MaxServ\FalS3\Service\Extraction;

use MaxServ\FalS3\Driver\AmazonS3Driver;

/**
 * Class PhpMetadataExtraction
 */
class PhpMetadataExtraction extends \Causal\Extractor\Service\Extraction\PhpMetadataExtraction
{
    /**
     * Returns all supported DriverTypes.
     *
     * Since some processors may only work for local files, and other
     * are especially made for processing files from remote.
     *
     * Returns array of strings with driver names of Drivers which are supported,
     * If the driver did not register a name, it's the class name.
     * empty array indicates no restrictions.
     *
     * @return array
     */
    public function getDriverRestrictions(): array
    {
        return array_merge_recursive(
            parent::getDriverRestrictions(),
            [
                AmazonS3Driver::DRIVER_KEY
            ]
        );
    }
}
