<?php
/**
 * Copyright © 2019 Paazl. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Paazl\CheckoutWidget\Cron;

use Magento\Sales\Model\ResourceModel\Order\Collection\Factory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Paazl\CheckoutWidget\Model\Api\Processor\SendToService;
use Paazl\CheckoutWidget\Setup\SetupSchema;
use Paazl\CheckoutWidget\Helper\General as GeneralHelper;

/**
 * Class SendOrders
 *
 * @package Paazl\CheckoutWidget\Cron
 */
class SendOrders
{

    /**
     * Retry time in seconds
     */
    const RETRY_TIME_SECONDS = 50400;

    /**
     * @var SendToService
     */
    private $sendToService;

    /**
     * @var Factory
     */
    private $orderCollectionFactory;

    /**
     * @var GeneralHelper
     */
    private $generalHelper;

    /**
     * SendOrders constructor.
     *
     * @param SendToService     $sendToService
     * @param CollectionFactory $orderCollectionFactory
     * @param GeneralHelper     $generalHelper
     */
    public function __construct(
        SendToService $sendToService,
        CollectionFactory $orderCollectionFactory,
        GeneralHelper $generalHelper
    ) {
        $this->sendToService = $sendToService;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->generalHelper = $generalHelper;
    }

    /**
     * @return array
     */
    public function execute()
    {
        $messages = [];

        $collection = $this->orderCollectionFactory->create();
        $collection->getSelect()
            ->joinInner(
                ['mpo' => $collection->getTable(SetupSchema::TABLE_ORDER)],
                'mpo.order_id = main_table.entity_id',
                []
            )
            ->where('mpo.ext_sent_at IS NULL')
            ->where(new \Zend_Db_Expr(
                'TIME_TO_SEC(TIMEDIFF(CURRENT_TIMESTAMP, `updated_at`)) < ' . self::RETRY_TIME_SECONDS
            ));

        foreach ($collection as $item) {
            try {
                $this->sendToService->process($item);
            } catch (\Exception $e) {
                $this->generalHelper->addTolog('exception', $e->getMessage());
            }
        }

        return $messages;
    }
}
