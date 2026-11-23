<?php

class   PaynetEasy_Paynet_SaleformController
extends PaynetEasy_Paynet_Controller_Abstract
{
    /**
     * Start order processing and redirect to PaynetEasy
     */
    public function redirectAction()
    {
        $orderId       = $this->getSession()->getLastRealOrderId();
        $callbackUrl   = Mage::getUrl("paynet/{$this->getModelCode()}/process",
                                       array('_secure' => true, 'order_id' => $orderId));

        // Only an order just placed with PaynetEasy starts a payment
        if (!$this->isStartableOrder($orderId)) {
            $this->_redirect('checkout/cart');
            return;
        }

        $this->markAsPending($orderId);

        try
        {
            $response = $this->getModel()
                             ->startSale($orderId, $callbackUrl);

            $this->getResponse()->setRedirect($response->getRedirectUrl());
        }
        catch (Exception $e)
        {
            Mage::log("There was an error occured for Order '{$orderId}': \n" . PaynetEasy_Paynet_Model_Abstract::safeErrorText($e), Zend_Log::ERR);
            Mage::log(PaynetEasy_Paynet_Model_Abstract::safeErrorText($e), Zend_Log::ERR, 'exception.log');
            $this->errorRedirect('technical_error');
        }
    }
}
