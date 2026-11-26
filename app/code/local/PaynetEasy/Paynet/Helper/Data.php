<?php

class PaynetEasy_Paynet_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Mark of a merchant key saved encrypted. A value without the mark is a key
     * saved by an earlier version of the plugin as it is.
     */
    const ENCRYPTED_KEY_PREFIX = 'pne-enc:';

    /**
     * Prepares the merchant key for the configuration storage
     *
     * @param       string      $key        Merchant key
     *
     * @return      string                  Value to save
     */
    public function encryptMerchantKey($key)
    {
        $key = (string) $key;

        if ($key === '')
        {
            return '';
        }

        return self::ENCRYPTED_KEY_PREFIX . Mage::helper('core')->encrypt($key);
    }

    /**
     * Gets the merchant key from the saved configuration value
     *
     * @param       string      $value      Saved value
     *
     * @return      string                  Merchant key
     */
    public function decryptMerchantKey($value)
    {
        $value = (string) $value;

        if (strpos($value, self::ENCRYPTED_KEY_PREFIX) !== 0)
        {
            return $value;
        }

        return (string) Mage::helper('core')->decrypt(substr($value, strlen(self::ENCRYPTED_KEY_PREFIX)));
    }
}
