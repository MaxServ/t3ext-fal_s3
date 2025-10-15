<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Driver;

use TYPO3\CMS\Core\Resource\Capabilities;
use TYPO3\CMS\Core\Resource\ResourceStorageInterface;

if (class_exists(Capabilities::class)) {
    // TYPO3 v13
    class AmazonS3Driver extends AbstractAmazonS3Driver
    {
        public function __construct(array $configuration = [])
        {
            parent::__construct($configuration);

            $this->capabilities = new Capabilities(
                Capabilities::CAPABILITY_BROWSABLE
                | Capabilities::CAPABILITY_PUBLIC
                | Capabilities::CAPABILITY_WRITABLE
            );
        }

        public function mergeConfigurationCapabilities($capabilities): Capabilities
        {
            $this->capabilities->and($capabilities);
            return $this->capabilities;
        }
    }
} else {
    // TYPO3 v12
    class AmazonS3Driver extends AbstractAmazonS3Driver
    {
        public function __construct(array $configuration = [])
        {
            parent::__construct($configuration);
            $this->capabilities = ResourceStorageInterface::CAPABILITY_BROWSABLE |
                ResourceStorageInterface::CAPABILITY_PUBLIC |
                ResourceStorageInterface::CAPABILITY_WRITABLE;
        }

        public function mergeConfigurationCapabilities($capabilities)
        {
            $this->capabilities &= $capabilities;
            return $this->capabilities;
        }
    }
}
