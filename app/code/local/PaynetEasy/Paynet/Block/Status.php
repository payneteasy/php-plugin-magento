<?php

/**
 * Paynet notification "form"
 */
class PaynetEasy_Paynet_Block_Status extends Mage_Core_Block_Template
{
    protected $_methodCode = 'paynet_status';

    /**
     * Set template with message
     */
    protected function _construct()
    {
        $this->setTemplate('paynet/status.phtml');
    }
}

