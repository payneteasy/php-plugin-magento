<?php

require_once Mage::getBaseDir('lib') . '/autoload.php';

use PaynetEasy\PaynetEasyApi\PaymentData\QueryConfig;

class PaynetEasy_Paynet_Model_System_Config_Source_Gatewaymode
{
    public function toOptionArray()
    {
        return array
        (
            array
            (
                'label' => 'Sandbox',
                'value' =>  QueryConfig::GATEWAY_MODE_SANDBOX,
            ),
            array(
                'label' => 'Production',
                'value' =>  QueryConfig::GATEWAY_MODE_PRODUCTION,
            ),
        );
    }
}
