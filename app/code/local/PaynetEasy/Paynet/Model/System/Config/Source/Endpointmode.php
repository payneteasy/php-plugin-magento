<?php

require_once Mage::getBaseDir('lib') . '/autoload.php';

class PaynetEasy_Paynet_Model_System_Config_Source_Endpointmode
{
    const ENDPOINT_MODE_SIMPLE = 'simple';
    const ENDPOINT_MODE_GROUP  = 'group';
    
    public function toOptionArray()
    {
        return array
        (
            array
            (
                'label' => 'Simple',
                'value' =>  self::ENDPOINT_MODE_SIMPLE,
            ),
            array(
                'label' => 'Group',
                'value' =>  self::ENDPOINT_MODE_GROUP,
            ),
        );
    }
}