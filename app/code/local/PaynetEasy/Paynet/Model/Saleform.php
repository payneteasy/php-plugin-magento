<?php

require_once Mage::getBaseDir('lib') . '/autoload.php';

use PaynetEasy\Paynet\OrderData\Order           as PaynetOrder;
use PaynetEasy\Paynet\OrderData\Customer        as PaynetCustomer;

use Mage_Sales_Model_Order                      as MageOrder;
use Mage_Core_Model_Store                       as MageStore;
use Mage_Sales_Model_Order_Payment_Transaction  as MagePaymentTransaction;

use PaynetEasy\Paynet\OrderProcessor;

use PaynetEasy\Paynet\Exception\ResponseException;

class   PaynetEasy_Paynet_Model_Saleform
extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Internal model code
     *
     * @var string
     */
    protected $_code          = 'paynet_saleform';

    /**
     * Name for the block with additional payment method information
     *
     * @var string
     */
    protected $_formBlockType = 'paynet/saleform';

    /**
     * Can use this payment method in administration panel?
     *
     * @var boolean
     */
    protected $_canUseInternal          = false;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     *
     * @var boolean
     */
    protected $_canUseForMultishipping  = false;

    /**
     * Call PaynetEasy_Paynet_Model_Saleform::initialize() or not
     *
     * @var boolean
     */
    protected $_isInitializeNeeded      = true;

    /**
     * Service for order processing
     *
     * @var     OrderProcessor
     */
    protected $_orderProcessor;

    /**
     * Instantiate state and set it to state object
     *
     * @param       string            $paymentAction
     * @param       Varien_Object     $stateObject
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setData('payment_action', $paymentAction);
        $stateObject->setState(MageOrder::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
        $stateObject->save();
    }

    /**
     * Return Order place redirect url
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $result = array();

        preg_match('#(?<=_)[a-z]+$#i', get_called_class(), $result);

        if (empty ($result))
        {
            throw new RuntimeException('Can not get model code from model class');
        }

        $modelCode = strtolower($result[0]);

        return Mage::getUrl("paynet/{$modelCode}/redirect", array('_secure' => true));
    }

    /**
     * Starts order processing.
     * Method executes query to paynet gateway and returns response from gateway.
     * After that user must be redirected to the Response::getRedirectUrl()
     *
     * @param       integer                         $orderId                Order ID
     * @param       string                          $callbackUrl            Url for final payment processing
     *
     * @return      \PaynetEasy\Paynet\Transport\Response                   Gateway response object
     */
    public function startSale($orderId, $callbackUrl)
    {
        $mageOrder      = $this->getMageOrder($orderId);
        $magePayment    = $mageOrder->getPayment();
        $paynetOrder    = $this->getPaynetOrder($mageOrder);
        $queryConfig    = $this->getQueryConfig($callbackUrl);

        try
        {
            $response = $this
                ->getOrderProcessor()
                ->executeQuery('sale-form', $queryConfig, $paynetOrder);
        }
        catch (Exception $e)
        {
            $this->cancelOrder($mageOrder, "Order '{$orderId}' cancelled, error occured");
            throw $e;
        }

        $magePayment->setTransactionId($paynetOrder->getPaynetOrderId());
        $magePayment
            ->addTransaction(MagePaymentTransaction::TYPE_PAYMENT)
            ->setIsClosed(0)
            ->save();

        $mageOrder->save();
        $magePayment->save();

        return $response;
    }

    /**
     * Finish order processing.
     * Method checks callnack data and returns object with them.
     * After that order processing result can be displayed.
     *
     * @param       integer             $orderId                    Order ID
     * @param       array               $callback                   Callback data from Paynet
     *
     * @return      PaynetEasy\Paynet\Transport\CallbackResponse    Callback object
     */
    public function finishSale($orderId, array $callback)
    {
        $mageOrder = $this->getMageOrder($orderId);

        if (!$mageOrder || !$mageOrder->getId())
        {
            throw new ResponseException("PaymentTransaction with id '{$orderId}' not found");
        }

        if ($mageOrder->getState() == MageOrder::STATE_PROCESSING)
        {
            return $mageOrder;
        }

        $paynetOrder = $this->getPaynetOrder($mageOrder);
        $queryConfig = $this->getQueryConfig();

        try
        {
            $callbackResponse = $this
                ->getOrderProcessor()
                ->executeCallback($callback, $queryConfig, $paynetOrder);
        }
        catch (Exception $e)
        {
            $this->cancelOrder($mageOrder, "Order '{$orderId}' cancelled, error occured");
            throw $e;
        }

        if ($paynetOrder->isApproved())
        {
            $this->completeOrder($mageOrder);
        }
        else
        {
            $this->cancelOrder($mageOrder, $paynetOrder->getLastError()->getMessage());
        }

        return $callbackResponse;
    }

    /**
     * Get service for order processing
     *
     * @return \PaynetEasy\Paynet\OrderProcessor
     */
    protected function getOrderProcessor()
    {
        if (is_null($this->_orderProcessor))
        {
            if ($this->getConfigData('is_sandbox'))
            {
                $gatewayUrl = $this->getConfigData('sandbox_api_url');
            }
            else
            {
                $gatewayUrl = $this->getConfigData('production_api_url');
            }

            $this->_orderProcessor = new OrderProcessor($gatewayUrl);
        }

        return $this->_orderProcessor;
    }

    /**
     * Get payment query config
     *
     * @param       string      $redirectUrl        Url for final payment processing
     *
     * @return      array                           Config
     */
    protected function getQueryConfig($redirectUrl = null)
    {
        $config = array
        (
            'end_point' => $this->getConfigData('endpoint_id'),
            'login'     => $this->getConfigData('merchant_login'),
            'control'   => $this->getConfigData('merchant_key'),
        );

        if ($redirectUrl)
        {
            $config['redirect_url']         = $redirectUrl;
            $config['server_callback_url']  = $redirectUrl;
        }

        return $config;
    }

    /**
     * Get Magento order object by ID
     *
     * @param       int                         $orderId            Order ID
     *
     * @return      Mage_Sales_Model_Order                          Order object
     */
    protected function getMageOrder($orderId)
    {
        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }

    /**
     * Get Paynet order object by Magento order object
     *
     * @param       Mage_Sales_Model_Order      $mageOrder          Magento order
     *
     * @return      \PaynetEasy\Paynet\OrderData\Order              Paynet order
     */
    protected function getPaynetOrder(MageOrder $mageOrder)
    {
        $mageAddress    = $mageOrder->getBillingAddress();
        $paynetOrder    = new PaynetOrder;
        $paynetCustomer = new PaynetCustomer;

        $paynetCustomer
            ->setCountry($mageAddress->getCountryId())
            ->setCity($mageAddress->getCity())
            ->setAddress($mageAddress->getStreet1())
            ->setZipCode($mageAddress->getPostcode())
            ->setPhone($mageAddress->getTelephone())
            ->setEmail($mageAddress->getEmail())
            ->setFirstName($mageOrder->getCustomerFirstname())
            ->setLastName($mageOrder->getCustomerLastname())
        ;

        if (strlen($mageAddress->getRegionCode()) == 2)
        {
            $paynetCustomer->setState($mageAddress->getRegionCode());
        }

        $paynetOrder
            ->setClientOrderId($mageOrder->getIncrementId())
            ->setPaynetOrderId($mageOrder->getPayment()->getLastTransId())
            ->setDescription($this->getPaynetOrderDescription($mageOrder))
            ->setAmount($mageOrder->getBaseGrandTotal())
            ->setCurrency($mageOrder->getOrderCurrencyCode())
            ->setIpAddress($mageOrder->getRemoteIp())
            ->setCustomer($paynetCustomer)
        ;

        return $paynetOrder;
    }

    /**
     * Get paynet order description by magento order
     *
     * @param       MageOrder      $order      Magento order
     *
     * @return      string                                  Paynet order description
     */
    protected function getPaynetOrderDescription(MageOrder $order)
    {
        return  Mage::helper('paynet')->__('Shopping in: ') . ' ' .
                Mage::getStoreConfig(MageStore::XML_PATH_STORE_STORE_NAME, $order->getStoreId()) . '; ' .
                Mage::helper('paynet')->__('Order ID: ') . ' ' . $order->getIncrementId();
    }

    /**
     * Cancel Magento order
     *
     * @param   Mage_Sales_Model_Order      $order          Order
     * @param   string                      $message        Cancel message
     */
    protected function cancelOrder(MageOrder $order, $message)
    {
        $order->cancel()
              ->setState(MageOrder::STATE_CANCELED, true, $message, true)
              ->sendOrderUpdateEmail()
              ->setIsNotified(true)
              ->save();
    }

    /**
     * Complete Magento order processing
     *
     * @param   Mage_Sales_Model_Order      $order          Order
     */
    protected function completeOrder(MageOrder $order)
    {
        $order->setState(MageOrder::STATE_PROCESSING, true)
              ->sendOrderUpdateEmail()
              ->setIsNotified(true)
              ->save();
    }
}
