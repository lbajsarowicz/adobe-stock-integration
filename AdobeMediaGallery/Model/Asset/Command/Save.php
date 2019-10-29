<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\AdobeMediaGallery\Model\Asset\Command;

use Magento\AdobeMediaGallery\Model\DataExtractor;
use Magento\AdobeMediaGalleryApi\Api\Data\AssetInterface;
use Magento\AdobeMediaGalleryApi\Model\Asset\Command\SaveInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Psr\Log\LoggerInterface;

/**
 * Class Save
 */
class Save implements SaveInterface
{
    private const TABLE_MEDIA_GALLERY_ASSET = 'media_gallery_asset';

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var DataExtractor
     */
    private $extractor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Save constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param DataExtractor $extractor
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        DataExtractor $extractor,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->extractor = $extractor;
        $this->logger = $logger;
    }

    /**
     * Save media assets
     *
     * @param AssetInterface $mediaAsset
     *
     * @return int
     * @throws CouldNotSaveException
     */
    public function execute(AssetInterface $mediaAsset): int
    {
        try {
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName(self::TABLE_MEDIA_GALLERY_ASSET);

            $connection->insertOnDuplicate($tableName, $this->extractor->extract($mediaAsset, AssetInterface::class));
            return (int) $connection->lastInsertId($tableName);
        } catch (\Exception $exception) {
            $message = __('An error occurred during media asset save: %1', $exception->getMessage());
            $this->logger->critical($message);
            throw new CouldNotSaveException($message, $exception);
        }
    }
}
