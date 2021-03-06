<?php
/**
 * Copyright © 2019 CHK. All rights reserved.
 * See COPYING.txt for license details.
 *
 * CHK_AmazonSNS extension
 * NOTICE OF LICENSE
 *
 * @category SMS
 * @package  CHK_AmazonSNS
 * @author   Koushik CH <info@chkoushik.com>
 */
namespace CHK\AmazonSNS\Observer\Order;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use CHK\AmazonSNS\Model\SNS\SendSms as SNS;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Sales\Model\Order;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Config\ScopeConfigInterface;



/**
 * Class OrderSave
 * @package CHK\AmazonSNS\Observer\Order
 */
class OrderSave implements ObserverInterface
{

    /**
     * @var CurrentCustomer
     */
    protected $_currCustomer;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var SNS
     */
    protected $_SNS;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SNS $SNS
     * @param CurrentCustomer $currentCustomer
     * @param Order $order
     * @param Customer $customer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SNS $SNS,
        CurrentCustomer $currentCustomer,
        Order $order,
        Customer $customer
    ) {
        $this->customer = $customer;
        $this->order = $order;
        $this->_currCustomer = $currentCustomer;
        $this->_scopeConfig = $scopeConfig;
        $this->_SNS = $SNS;


    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $orderTotal = $order->getBaseGrandTotal();
        $statusBefore =  $order->getOrigData('status');
        $statusAfter = $order->getStatus();
        if (($statusBefore == "pending" && $statusAfter == "processing") && ($this->_scopeConfig->getValue('chk/content/enabled_order'))) {
            $content = $this->_scopeConfig
            ->getValue('chk/content/order_success');
        } elseif (($statusBefore != $statusAfter) && ($this->_scopeConfig->getValue('chk/content/enabled_update'))) {
            $content = $this->_scopeConfig
            ->getValue('chk/content/update_status');
        }
        /** @var CustomerInterface $customer */

        $phoneNumber=$order->getBillingAddress()->getTelephone();
        $customerName=$order->getBillingAddress()->getFirstname();
        $increment_id = $order->getIncrementId();
        $tracking = $order->getTracksCollection()->getData();
        $replaceString = [
            '{{order_id}}' => $increment_id,
            '{{customer_name}}' => $customerName,
            '{{order_base_grand_totals}}' => $orderTotal,
            '{{old_status}}' => $statusBefore,
            '{{new_status}}' => $statusAfter,
            '{{tracking_number}}' => isset($tracking[0]['track_number']) ? $tracking[0]['track_number'] : ""
        ];
        $status = $this->_scopeConfig
        ->getValue('chk/mobile_login_option/status');
        if ($phoneNumber && isset($content) && $status) {
            $newContent = strtr($content, $replaceString);
            if ($this->_SNS->isEnabled()) {
                $this->_SNS->sendSMS($newContent, $phoneNumber);
            }
        }
    }
}
