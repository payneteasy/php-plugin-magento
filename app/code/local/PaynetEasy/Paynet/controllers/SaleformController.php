<?php

class   PaynetEasy_Paynet_SaleformController
extends         Mage_Core_Controller_Front_Action
{
    /**
     * Model instance
     *
     * @var PaynetEasy_Paynet_Model_Saleform
     */
    protected $_model;

    /**
     * Model code
     *
     * @var string
     */
    protected $_modelCode;

    /**
     * Start order processing and redirect to Paynet
     */
    public function redirectAction()
    {
        $orderId       = $this->getSession()->getLastRealOrderId();
        $callbackUrl   = Mage::getUrl("paynet/{$this->getModelCode()}/process",
                                       array('_secure' => true, 'order_id' => $orderId));

        try
        {
            Mage::log('Get payment form from Paynet', Zend_Log::DEBUG);
            $response = $this->getModel()
                             ->startSale($orderId, $callbackUrl);

            Mage::log('Payment form received from Paynet', Zend_Log::DEBUG);
            $this->getResponse()->setRedirect($response->getRedirectUrl());
        }
        catch (Exception $e)
        {
            Mage::log("There was an error occured for Order '{$orderId}': \n{$e->getMessage()}", Zend_Log::ERR);
            Mage::logException($e);
            $this->errorRedirect('There was a technical error occured.');
        }
    }

    /**
     * Receive paynet callback data, finish order processing
     * and redirect to page with payment result
     */
    public function processAction()
    {
        $orderId  = $this->getRequest()->order_id;
        $callback = $_REQUEST;

        try
        {
            Mage::log('Process Paynet payment form', Zend_Log::DEBUG);
            $response = $this->getModel()
                 ->finishSale($orderId, $callback);
        }
        catch (Exception $e)
        {
            Mage::log("There was an error occured for Order '{$orderId}': \n{$e->getMessage()}", Zend_Log::ERR);
            Mage::log("Callback data: " . print_r($callback, true), Zend_Log::DEBUG);
            Mage::logException($e);
            $this->errorRedirect('There was a technical error occured.');

            return;
        }

        if ($response->isApproved())
        {
            Mage::log('Payment form succesfully processed', Zend_Log::DEBUG);
            $this->successRedirect();
        }
        else
        {
            Mage::log("Payment is not passed", Zend_Log::DEBUG);
            Mage::log("Callback data: " . print_r($callback, true), Zend_Log::DEBUG);
            $this->errorRedirect('Your payment is not passed.');
        }
    }

    /**
     * Get model code
     *
     * @return      string      Model code
     */
    protected function getModelCode()
    {
        if (empty ($this->_modelCode))
        {
            $result = array();

            preg_match('#(?<=_)[a-z]+(?=Controller)#i', get_called_class(), $result);

            if (empty ($result))
            {
                throw new RuntimeException('Can not get model code from controller class');
            }

            $this->_modelCode = strtolower($result[0]);
        }

        return $this->_modelCode;
    }

    /**
     * Get model instance
     *
     * @param       string          $model_name         Model name to instantiate
     *
     * @return      PaynetEasy_Paynet_Model_Saleform    Model instance
     */
    protected function getModel()
    {
        if (is_null($this->_model))
        {
            $this->_model = Mage::getModel("paynet/{$this->getModelCode()}");
        }

        return $this->_model;
    }

    /**
     * Get session object
     *
     * @return      Mage_Checkout_Model_Session
     */
    protected function getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Redirect if payment not passed
     *
     * @param       string      $errorMessage        Error messahe
     */
    protected function errorRedirect($errorMessage)
    {
        $this->getSession()->addError(Mage::helper('paynet')->__($errorMessage));

        $this->_redirect('checkout/cart');
    }

    /**
     * Redirect if payment successfully processing
     */
    protected function successRedirect()
    {
        $this->getSession()->getQuote()->setIsActive(false)->save();

        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    }
}
