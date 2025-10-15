<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Middleware;

use MaxServ\FalS3\Driver\AmazonS3Driver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Resource\StorageRepository;

class FalS3PreconnectMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected StorageRepository $storageRepository
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $linkHeaders = array_merge($response->getHeader('Link'), $this->getPreconnectHeaders());
        $linkHeaders = array_unique($linkHeaders);

        if (count($linkHeaders) > 0) {
            $response = $response->withHeader('Link', $linkHeaders);
        }

        return $response;
    }

    protected function getPreconnectHeaders(): array
    {
        $linkHeaders = [];
        $storages = $this->storageRepository->findByStorageType(AmazonS3Driver::DRIVER_KEY);
        foreach ($storages as $storage) {
            $key = $storage->getConfiguration()['configurationKey'] ?? '';
            if ($key === '' || !$storage->isOnline()) {
                continue;
            }

            $baseUrl = trim(
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$key]['publicBaseUrl'] ?? ''
            );

            if ($baseUrl !== '') {
                $parsed = parse_url($baseUrl);
                $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
                $linkHeaders[] = '<' . $baseUrl . '>; rel="preconnect"';
            }
        }

        return $linkHeaders;
    }
}
