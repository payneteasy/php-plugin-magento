<?php

require_once Mage::getBaseDir('lib') . '/autoload.php';

use Mage_Sales_Model_Order as MageOrder;

class   PaynetEasy_Paynet_Model_Sale
extends PaynetEasy_Paynet_Model_Abstract
{
    /**
     * {@inheritdoc}
     */
    protected $_code                = 'paynet_sale';

    /**
     * {@inheritdoc}
     */
    protected $_formBlockType       = 'paynet/sale';

    /**
     * {@inheritdoc}
     */
    protected $_initialApiMethod    = 'sale';

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object))
        {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();

        $info
            ->setCcOwner($data->getCcOwner())
            ->setCcNumber($data->getCcNumber())
            ->setCcCid($data->getCcCid())
            ->setCcExpMonth($data->getCcExpMonth())
            ->setCcExpYear($data->getCcExpYear())
        ;

        return $this;
    }

    /**
     * Prepare info instance for save
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function prepareSave()
    {
        $info = $this->getInfoInstance();

        $info->setCcNumberEnc($info->encrypt($info->getCcNumber()));
        $info->setCcCidEnc($info->encrypt($info->getCcCid()));

        $info
            ->setCcNumber(null)
            ->setCcCid(null);

        return $this;
    }

    /**
     * Get Paynet payment transaction object by Magento order object
     *
     * @param       MageOrder       $mageOrder          Magento order
     * @param       string          $redirectUrl        Url for final payment processing
     *
     * @return      PaynetEasy\PaynetEasyApi\PaymentData\PaymentTransaction     Paynet payment transaction
     */
    protected function getPaynetTransaction(MageOrder $mageOrder, $redirectUrl = null)
    {
        $paynetTransaction = parent::getPaynetTransaction($mageOrder, $redirectUrl);

        $this->addCreditCardData($paynetTransaction, $mageOrder);

        return $paynetTransaction;
    }
}
