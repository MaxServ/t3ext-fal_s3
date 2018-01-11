<?php
namespace MaxServ\FalS3\Resource;

/**
 * Class File
 * @package MaxServ\FalS3\Resource
 */
class File extends \TYPO3\CMS\Core\Resource\File
{
    /**
     * Returns the date (as UNIX timestamp) the file was last modified.
     *
     * @throws \RuntimeException
     * @return int
     */
    public function getModificationTime()
    {
        if ($this->deleted) {
            throw new \RuntimeException('File has been deleted.', 1329821488);
        }
        if ($this->storage->getDriverType() === 'MaxServ.FalS3') {
            $fileInfo = $this->storage->getFileInfoByIdentifier($this->identifier);
            return $fileInfo['mtime'];
        }
        return (int)$this->getProperty('modification_date');
    }
}