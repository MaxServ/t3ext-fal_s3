<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Resource;

use TYPO3\CMS\Core\Resource\ProcessedFile;

class CrossStorageAwareProcessedFile extends ProcessedFile
{
    public function getForLocalProcessing(bool $writable = true): string
    {
        // TYPO3 14 Core regression workaround: when the processed file is just a pointer
        // to the original (no actual resize happened), delegate to the original's
        // storage instead of asking the *processing* storage's driver to find
        // the original's identifier - those buckets are not the same when
        // sys_file_storage.processingfolder uses cross-storage syntax (e.g. "2:_processed_/").
        if ($this->usesOriginalFile()) {
            return $this->getOriginalFile()->getForLocalProcessing($writable);
        }
        return parent::getForLocalProcessing($writable);
    }
}
