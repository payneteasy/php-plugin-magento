<?php

/**
 * Saves the merchant key encrypted. A key saved by an earlier version
 * of the plugin as it is stays readable and is encrypted on the next save.
 */
class   PaynetEasy_Paynet_Model_System_Config_Backend_Merchantkey
extends Mage_Core_Model_Config_Data
{
    /**
     * Decrypt the value after loading
     */
    protected function _afterLoad()
    {
        $this->setValue(Mage::helper('paynet')->decryptMerchantKey($this->getValue()));
    }

    /**
     * Encrypt the value before saving
     */
    protected function _beforeSave()
    {
        $value = (string) $this->getValue();

        // The form shows asterisks instead of the key: they mean "not changed"
        if (preg_match('/^\*+$/', $value))
        {
            $value = Mage::helper('paynet')->decryptMerchantKey($this->getOldValue());
        }

        $this->setValue(Mage::helper('paynet')->encryptMerchantKey($value));
    }
}
