<?php

require_once Mage::getBaseDir('lib') . '/autoload.php';

use PaynetEasy\PaynetEasyApi\PaymentData\PaymentTransaction as PaynetTransaction;

use PaynetEasy\PaynetEasyApi\Util\Validator;
use PaynetEasy\PaynetEasyApi\Transport\CallbackResponse;

use Mage_Sales_Model_Order                                  as MageOrder;
use Mage_Sales_Model_Order_Payment_Transaction              as MageTransaction;
use PaynetEasy_Paynet_Model_System_Config_Source_Endpointmode as EndpointMode;

use PaynetEasy\PaynetEasyApi\PaymentProcessor;

use PaynetEasy\PaynetEasyApi\Exception\ResponseException;

abstract class  PaynetEasy_Paynet_Model_Abstract
extends         Mage_Payment_Model_Method_Abstract
{
    /**
     * Internal model code
     *
     * @var string
     */
    protected $_code;

    /**
     * Name for the block with additional payment method information
     *
     * @var string
     */
    protected $_formBlockType;

    /**
     * Initial API method for model payment flow
     *
     * @var string
     */
    protected $_initialApiMethod;

    /**
     * Is this payment method integrates third-party payment system?
     * 
     * @var boolean
     */
    protected $_isGateway               = true;
    
    /**
     * Can use this payment method for payment authorization?
     * 
     * @var type 
     */
    protected $_canAuthorize            = true;

    /**
     * Can use this payment method for payment capture?
     * 
     * @var type 
     */
    protected $_canCapture              = true;

    /**
     * Can use this payment method for payment voiding?
     * 
     * @var type 
     */
    protected $_canVoid                 = false;

    /**
     * Can use this payment method for regular checkout process?
     * 
     * @var type 
     */
    protected $_canUseCheckout          = true;
    
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

        if (empty($this->_code))
        {
            throw new RuntimeException('Internal model code must be setted');
        }

        if (empty($this->_formBlockType))
        {
            throw new RuntimeException('Name for the block with additional payment method information must be setted');
        }

        if (empty($this->_initialApiMethod))
        {
            throw new RuntimeException('Initial API method for model payment flow must be setted');
        }
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
     * Method executes query to PaynetEasy gateway and returns response from gateway.
     * After that user must be:
     * - redirected to the Response::getRedirectUrl() (for *-form queries)
     * - payment status must be updated (for other queries)
     *
     * @param       integer                         $orderId                Order ID
     * @param       string                          $callbackUrl            Url for final payment processing
     *
     * @return      \PaynetEasy\PaynetEasyApi\Transport\Response            Gateway response object
     */
    public function startSale($orderId, $callbackUrl)
    {
        $mageOrder          = $this->getMageOrder($orderId);
        $magePayment        = $mageOrder->getPayment();
        $paynetTransaction  = $this->getPaynetTransaction($mageOrder, $callbackUrl);

        try
        {
            $response = $this
                ->getPaymentProcessor()
                ->executeQuery($this->_initialApiMethod, $paynetTransaction);
        }
        catch (Exception $e)
        {
            $this->cancelOrder($mageOrder, "Order '{$orderId}' cancelled, error occured");
            throw $e;
        }

        $magePayment->setTransactionId($paynetTransaction->getPayment()->getPaynetId());
        $magePayment
            ->addTransaction(MageTransaction::TYPE_PAYMENT)
            ->setIsClosed(0)
            ->save();

        $mageOrder->save();
        $magePayment->save();

        return $response;
    }

    /**
     * Updates payment status.
     * Method executes query to PaynetEasy gateway and returns response from gateway.
     * After this method call must be one of the following actions:
     * - Display html from Response::getHtml() if Response::isShowHtmlNeeded() is true
     * - Update payment status if Response::isStatusUpdateNeeded() is true
     * - Continue order processing otherwise
     *
     * @param       integer                         $orderId                Order ID
     *
     * @return      \PaynetEasy\PaynetEasyApi\Transport\Response            Gateway response object
     */
    public function updateStatus($orderId)
    {
        $mageOrder          = $this->getMageOrder($orderId);
        $paynetTransaction  = $this->getPaynetTransaction($mageOrder);

        try
        {
            $response = $this
                ->getPaymentProcessor()
                ->executeQuery('status', $paynetTransaction);
        }
        catch (Exception $e)
        {
            $this->cancelOrder($mageOrder, "Order '{$orderId}' cancelled, error occured");
            throw $e;
        }

        if ($paynetTransaction->isApproved())
        {
            $this->completeOrder($mageOrder);
        }
        elseif ($paynetTransaction->isFinished())
        {
            $this->cancelOrder($mageOrder, $paynetTransaction->getLastError()->getMessage());
        }
        else
        {
            $mageOrder->save();
        }

        return $response;
    }

    /**
     * Finish order processing.
     * Method checks callback data and returns object with them.
     * After that order processing result can be displayed.
     *
     * @param       integer     $orderId        Order ID
     * @param       array       $callback       Callback data from Paynet
     *
     * @return      CallbackResponse            Callback object
     */
    public function finishSale($orderId, array $callback)
    {
        $mageOrder = $this->getMageOrder($orderId);
        $callbackResponse = new CallbackResponse($callback);

        if (!$mageOrder || !$mageOrder->getId())
        {
            throw new ResponseException("Order with id '{$orderId}' not found");
        }

        // already finished, no further actions
        if ($mageOrder->getState() == MageOrder::STATE_PROCESSING)
        {
            return $callbackResponse;
        }

        $paynetTransaction = $this->getPaynetTransaction($mageOrder);
        $paynetTransaction->setStatus(PaynetTransaction::STATUS_PROCESSING);

        try
        {
            $this
                ->getPaymentProcessor()
                ->processCustomerReturn(new CallbackResponse($callback), $paynetTransaction);
        }
        catch (Exception $e)
        {
            $this->cancelOrder($mageOrder, "Order '{$orderId}' cancelled, error occured");
            throw $e;
        }

        if ($paynetTransaction->isApproved())
        {
            $this->completeOrder($mageOrder);
        }
        else
        {
            $this->cancelOrder($mageOrder, $paynetTransaction->getLastError()->getMessage());
        }

        return $callbackResponse;
    }

    /**
     * Get service for payment processing
     *
     * @return \PaynetEasy\PaynetEasyApi\PaymentProcessor
     */
    protected function getPaymentProcessor()
    {
        static $processor = null;

        if (empty($processor))
        {
            $processor = new PaymentProcessor;
        }

        return $processor;
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
     * Get PaynetEasy payment transaction object by Magento order object
     *
     * @param       MageOrder       $mageOrder          Magento order
     * @param       string          $redirectUrl        Url for final payment processing
     *
     * @return      PaynetTransaction                   PaynetEasy payment transaction
     */
    protected function getPaynetTransaction(MageOrder $mageOrder, $redirectUrl = null)
    {
        $paynetTransaction  = new PaynetTransaction;

        $this->addPaymentData($paynetTransaction, $mageOrder);
        $this->addCustomerData($paynetTransaction, $mageOrder);
        $this->addBillingAdressData($paynetTransaction, $mageOrder);
        $this->addQueryConfigData($paynetTransaction, $redirectUrl);

        return $paynetTransaction;
    }

    /**
     * Add payment data to PaynetEasy payment transaction
     *
     * @param       PaynetTransaction       $paynetTransaction      PaynetEasy payment transaction
     * @param       MageOrder               $mageOrder              Magento order
     *
     * @return      PaynetTransaction                               PaynetEasy payment transaction
     */
    protected function addPaymentData(PaynetTransaction $paynetTransaction, MageOrder $mageOrder)
    {
        $paynetTransaction
            ->getPayment()
            ->setClientId($mageOrder->getIncrementId())
            ->setPaynetId($mageOrder->getPayment()->getLastTransId())
            ->setDescription($this->getPaynetPaymentDescription($mageOrder))
            ->setAmount($mageOrder->getBaseGrandTotal())
            ->setCurrency($mageOrder->getOrderCurrencyCode())
        ;
    }

    /**
     * Add customer data to PaynetEasy payment
     *
     * @param       PaynetTransaction       $paynetTransaction      PaynetEasy payment transaction
     * @param       MageOrder               $mageOrder              Magento order
     *
     * @return      PaynetTransaction                               PaynetEasy payment transaction
     */
    protected function addCustomerData(PaynetTransaction $paynetTransaction, MageOrder $mageOrder)
    {
        $mageAddress = $mageOrder->getBillingAddress();

        $paynetTransaction
            ->getPayment()
            ->getCustomer()
            ->setEmail($mageAddress->getEmail())
            ->setFirstName($mageOrder->getCustomerFirstname())
            ->setLastName($mageOrder->getCustomerLastname())
            ->setIpAddress($mageOrder->getRemoteIp())
        ;
    }

    /**
     * Add customer data to PaynetEasy payment
     *
     * @param       PaynetTransaction       $paynetTransaction      PaynetEasy payment transaction
     * @param       MageOrder               $mageOrder              Magento order
     *
     * @return      PaynetTransaction                               PaynetEasy payment transaction
     */
    protected function addBillingAdressData(PaynetTransaction $paynetTransaction, MageOrder $mageOrder)
    {
        $mageAddress = $mageOrder->getBillingAddress();

        $paynetTransaction
            ->getPayment()
            ->getBillingAddress()
            ->setCountry($mageAddress->getCountryId())
            ->setCity($mageAddress->getCity())
            ->setFirstLine($mageAddress->getStreet1())
            ->setZipCode($mageAddress->getPostcode())
            ->setPhone($mageAddress->getTelephone())
        ;

        if (Validator::validateByRule($mageAddress->getRegionCode(), Validator::COUNTRY, false))
        {
            $paynetTransaction
                ->getPayment()
                ->getBillingAddress()
                ->setState($mageAddress->getRegionCode())
            ;
        }
    }

    /**
     * Add credit card data to PaynetEasy payment
     *
     * @param       PaynetTransaction       $paynetTransaction      PaynetEasy payment transaction
     * @param       MageOrder               $mageOrder              Magento order
     *
     * @return      PaynetTransaction                               PaynetEasy payment transaction
     */
    protected function addCreditCardData(PaynetTransaction $paynetTransaction, MageOrder $mageOrder)
    {
        $magePayment        = Mage::getModel('sales/quote')
            ->load($mageOrder->getQuoteId())
            ->getPayment()
        ;

        $paynetTransaction
            ->getPayment()
            ->getCreditCard()
            ->setCardPrintedName($magePayment->getCcOwner())
            ->setCreditCardNumber($magePayment->getCcNumber())
            ->setExpireMonth($magePayment->getCcExpMonth())
            ->setExpireYear(substr($magePayment->getCcExpYear(), 2))
            ->setCvv2($magePayment->getCcCid())
        ;
    }

    /**
     * Add query config data to PaynetEasy payment transaction
     *
     * @param       PaynetTransaction       $paynetTransaction      PaynetEasy payment transaction
     * @param       string                  $redirectUrl            Url for final payment processing
     *
     * @return      PaynetTransaction                               PaynetEasy payment transaction
     */
    protected function addQueryConfigData(PaynetTransaction $paynetTransaction, $redirectUrl = null)
    {
        $queryConfig = $paynetTransaction->getQueryConfig();
        
        $queryConfig 
            ->setLogin($this->getConfigData('merchant_login'))
            ->setSigningKey($this->getConfigData('merchant_key'))
            ->setGatewayMode($this->getConfigData('gateway_mode'))
            ->setGatewayUrlSandbox($this->getConfigData('sandbox_api_url'))
            ->setGatewayUrlProduction($this->getConfigData('production_api_url'))
        ;

        if ($this->getConfigData('endpoint_mode') == EndpointMode::ENDPOINT_MODE_SIMPLE) {
            $queryConfig->setEndPoint($this->getConfigData('endpoint_id'));
        }
        elseif ($this->getConfigData('endpoint_mode') == EndpointMode::ENDPOINT_MODE_GROUP) {
            $queryConfig->setEndPointGroup($this->getConfigData('endpoint_id'));
        }
        else {
            throw new InvalidArgumentException("Unknown endpoint mode: '{$this->getConfigData('endpoint_mode')}'");
        }
        
        if (Validator::validateByRule($redirectUrl, Validator::URL, false))
        {
            $queryConfig
                ->setRedirectUrl($redirectUrl)
                ->setCallbackUrl($redirectUrl)
            ;
        }
    }

    /**
     * Get PaynetEasy payment description by magento order
     *
     * @param       MageOrder      $order      Magento order
     *
     * @return      string                     PaynetEasy order description
     */
    protected function getPaynetPaymentDescription(MageOrder $order)
    {
        return  Mage::helper('paynet')->__('shopping_in') . ': ' .
                Mage::app()->getStore($order->getStoreId())->getGroup()->getName() . '; ' .
                Mage::helper('paynet')->__('order_id') . ': ' . $order->getIncrementId();
    }

    /**
     * Cancel Magento order
     *
     * @param   MageOrder       $order          Order
     * @param   string          $message        Cancel message
     */
    protected function cancelOrder(MageOrder $order, $message)
    {
        $order->cancel()
              ->setState(MageOrder::STATE_CANCELED, true, $message, true)
              ->sendOrderUpdateEmail()
              ->setIsNotified(true)
              ->save();
        
        $payment = $order->getPayment();
        $payment->setStatus(self::STATUS_DECLINED);
        $payment->setIsTransactionDenied(true);
        $payment->save();
    }

    /**
     * Complete Magento order processing
     *
     * @param   MageOrde        $order          Order
     */
    protected function completeOrder(MageOrder $order)
    {
        $order->setState(MageOrder::STATE_PROCESSING, true)
              ->sendOrderUpdateEmail()
              ->setIsNotified(true)
              ->save();
        
        $payment = $order->getPayment();
        $payment->setStatus(self::STATUS_APPROVED);
        $payment->setIsTransactionApproved(true);
        $payment->save();
        
        $order->save();
    }
}
