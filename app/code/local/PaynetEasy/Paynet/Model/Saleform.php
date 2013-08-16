<?php

require_once Mage::getBaseDir('lib') . '/autoload.php';

class   PaynetEasy_Paynet_Model_Saleform
extends PaynetEasy_Paynet_Model_Abstract
{
    /**
     * {@inheritdoc}
     */
    protected $_code                = 'paynet_saleform';

    /**
     * {@inheritdoc}
     */
    protected $_formBlockType       = 'paynet/saleform';

    /**
     * {@inheritdoc}
     */
    protected $_initialApiMethod    = 'sale-form';
}
