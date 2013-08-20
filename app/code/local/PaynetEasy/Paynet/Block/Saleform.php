<?php

/**
 * PaynetEasy "sale-form" method block for checkout form
 */
class PaynetEasy_Paynet_Block_Saleform extends Mage_Payment_Block_Form
{
    protected $_methodCode = 'paynet_saleform';

    /**
     * Set template with message
     */
    protected function _construct()
    {
        $this->setTemplate('paynet/saleform.phtml');
    }
}
