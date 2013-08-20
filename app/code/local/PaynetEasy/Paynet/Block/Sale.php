<?php

/**
 * PaynetEasy "sale" method block for checkout form
 */
class PaynetEasy_Paynet_Block_Sale extends Mage_Payment_Block_Form
{
    protected $_methodCode = 'paynet_sale';

    /**
     * Set template with message
     */
    protected function _construct()
    {
        $this->setTemplate('paynet/sale.phtml');
    }

    /**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
    protected function _getConfig()
    {
        return Mage::getSingleton('payment/config');
    }

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    public function getCcMonths()
    {
        $months = $this->getData('cc_months');

        if (is_null($months))
        {
            $months = array(0 => $this->__('Month')) + $this->_getConfig()->getMonths();
            $this->setData('cc_months', $months);
        }

        return $months;
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    public function getCcYears()
    {
        $years = $this->getData('cc_years');

        if (is_null($years))
        {
            $years = array(0 => $this->__('Year')) + $this->_getConfig()->getYears();
            $this->setData('cc_years', $years);
        }

        return $years;
    }
}
