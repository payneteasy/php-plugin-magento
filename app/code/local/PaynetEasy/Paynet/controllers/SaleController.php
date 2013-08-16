<?php

class   PaynetEasy_Paynet_SaleController
extends Mage_Core_Controller_Front_Action
{
    /**
     * Model instance
     *
     * @var PaynetEasy_Paynet_Model_Sale
     */
    protected $_model;

    /**
     * Model code
     *
     * @var string
     */
    protected $_modelCode;

    /**
     * Start order processing and redirect to PaynetEasy
     */
    public function redirectAction()
    {
        $orderId       = $this->getSession()->getLastRealOrderId();
        $callbackUrl   = Mage::getUrl("paynet/{$this->getModelCode()}/process",
                                       array('_secure' => true, 'order_id' => $orderId));

        try
        {
            $this
                ->getModel()
                ->startSale($orderId, $callbackUrl)
            ;
        }
        catch (Exception $e)
        {
            Mage::log("There was an error occured for Order '{$orderId}': \n{$e->getMessage()}", Zend_Log::ERR);
            Mage::logException($e);
            return $this->errorRedirect('technical_error');
        }

        $this
            ->getResponse()
            ->setRedirect(Mage::getUrl("paynet/{$this->getModelCode()}/status",
                                       array('_secure' => true, 'order_id' => $orderId)))
        ;
    }

    /**
     * Update payment status
     */
    public function statusAction()
    {
        $orderId   = $this->getRequest()->order_id;

        try
        {
            $response = $this
                ->getModel()
                ->updateStatus($orderId)
            ;
        }
        catch (Exception $e)
        {
            Mage::log("There was an error occured for Order '{$orderId}': \n{$e->getMessage()}", Zend_Log::ERR);
            Mage::logException($e);
            return $this->errorRedirect('technical_error');
        }

        // reload current page
        if ($response->isStatusUpdateNeeded())
        {
            $statusUrl = Mage::getUrl("paynet/{$this->getModelCode()}/status",
                                array('_secure' => true, 'order_id' => $orderId));

            $this->loadLayout();
            $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
            $this->getLayout()->getBlock('head')->setTitle($this->__('status_title'));
            $this->getLayout()->getBlock('paynet_status')->assign('formAction', $statusUrl);
            $this->renderLayout();
        }
        // 3D-auth process
        elseif ($response->isShowHtmlNeeded())
        {
            $this
                ->getResponse()
                ->setBody($response->getHtml())
            ;
        }
        elseif ($response->isApproved())
        {
            $this->successRedirect();
        }
        else
        {
            Mage::log("Payment is not passed", Zend_Log::DEBUG);
            $this->errorRedirect('payment_not_passed');
        }
    }

    /**
     * Receive PaynetEasy callback data, finish order processing
     * and redirect to page with payment result
     */
    public function processAction()
    {
        $orderId  = $this->getRequest()->order_id;
        $callback = $_REQUEST;

        try
        {
            $response = $this->getModel()
                 ->finishSale($orderId, $callback);
        }
        catch (Exception $e)
        {
            Mage::log("There was an error occured for Order '{$orderId}': \n{$e->getMessage()}", Zend_Log::ERR);
            Mage::log("Callback data: " . print_r($callback, true), Zend_Log::DEBUG);
            Mage::logException($e);
            return $this->errorRedirect('technical_error');
        }

        if ($response->isApproved())
        {
            $this->successRedirect();
        }
        else
        {
            Mage::log("Payment is not passed", Zend_Log::DEBUG);
            Mage::log("Callback data: " . print_r($callback, true), Zend_Log::DEBUG);
            $this->errorRedirect('payment_not_passed');
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
     * @return      PaynetEasy_Paynet_Model_Sale        Model instance
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
