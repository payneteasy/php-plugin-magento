<?php

require_once Mage::getBaseDir('lib') . '/autoload.php';

use PaynetEasy\Paynet\OrderData\Order;
use PaynetEasy\Paynet\OrderData\Customer;

use PaynetEasy\Paynet\OrderProcessor;

use PaynetEasy\Paynet\Exception\ResponseException;

class   PaynetEasy_Paynet_Model_Saleform
extends Mage_Payment_Model_Method_Abstract
{
    protected $_code          = 'paynet_saleform';
    protected $_formBlockType = 'paynet/saleform';

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = false;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = false;

    protected $_isInitializeNeeded      = true;

    /**
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
        $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
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
     * Метод выполняет запрос к платежной форме
     *
     * @param       integer                         $orderId                ID заказа
     * @param       string                          $callbackUrl            Ссылка, на которую должен прийти ответ
     *
     * @return      \PaynetEasy\Paynet\Transport\Response                   Ответ от Paynet
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
            ->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT)
            ->setIsClosed(0)
            ->save();

        $mageOrder->save();
        $magePayment->save();

        return $response;
    }

    /**
     * @param       integer     $orderId            ID заказа
     * @param       array       $callback           Данные, полученные от Paynet
     *
     * @return      CallbackResponse                Callback object
     */
    public function finishSale($orderId, $callback)
    {
        $mageOrder = $this->getMageOrder($orderId);

        if (!$mageOrder || !$mageOrder->getId())
        {
            throw new ResponseException("PaymentTransaction with id '{$orderId}' not found");
        }

        if ($mageOrder->getState() == Mage_Sales_Model_Order::STATE_PROCESSING)
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
     * @return array
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
     * @param   int     $orderId
     *
     * @return  Mage_Sales_Model_Order
     */
    protected function getMageOrder($orderId)
    {
        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }

    /**
     * @param   Mage_Sales_Model_Order      $mageOrder
     *
     * @return  \PaynetEasy\Paynet\OrderData\Order
     */
    protected function getPaynetOrder(Mage_Sales_Model_Order $mageOrder)
    {
        $mageAddress    = $mageOrder->getBillingAddress();
        $paynetOrder    = new Order;
        $paynetCustomer = new Customer;

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
     * @param   Mage_Sales_Model_Order      $order
     *
     * @return  string
     */
    protected function getPaynetOrderDescription($order)
    {
        return  Mage::helper('paynet')->__('Shopping in: ') . ' ' .
                Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_STORE_STORE_NAME, $order->getStoreId()) . '; ' .
                Mage::helper('paynet')->__('Order ID: ') . ' ' . $order->getIncrementId();
    }

    /**
     * @param   Mage_Sales_Model_Order      $order
     * @param   string                      $message
     */
    protected function cancelOrder($order, $message)
    {
        $order->cancel()
              ->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message, true)
              ->sendOrderUpdateEmail()
              ->setIsNotified(true)
              ->save();
    }

    /**
     * @param   Mage_Sales_Model_Order      $order
     */
    protected function completeOrder($order)
    {
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)
              ->sendOrderUpdateEmail()
              ->setIsNotified(true)
              ->save();
    }
}