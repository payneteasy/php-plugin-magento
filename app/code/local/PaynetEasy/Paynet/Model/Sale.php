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
            ->setCcOwner(trim((string) $data->getCcOwner()))
            // Customers type the number with spaces or dashes: send digits only
            ->setCcNumber(preg_replace('/\D+/', '', (string) $data->getCcNumber()))
            ->setCcCid(preg_replace('/\D+/', '', (string) $data->getCcCid()))
            ->setCcExpMonth($data->getCcExpMonth())
            ->setCcExpYear($data->getCcExpYear())
        ;

        return $this;
    }

    /**
     * Prepare info instance for save.
     *
     * The card number and CVV are not saved with the payment: they are kept
     * encrypted in the checkout session until the sale request is sent.
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function prepareSave()
    {
        $info = $this->getInfoInstance();

        if ($info->getCcNumber())
        {
            $card = json_encode(array
            (
                'number' => (string) $info->getCcNumber(),
                'cid'    => (string) $info->getCcCid(),
            ));

            Mage::getSingleton('checkout/session')->setPaynetEasyCard(Mage::helper('core')->encrypt($card));

            $info->setCcLast4(substr((string) $info->getCcNumber(), -4));
        }

        $info
            ->setCcNumber(null)
            ->setCcCid(null)
            ->setCcNumberEnc(null)
            ->setCcCidEnc(null);

        return $this;
    }

    /**
     * Takes the card number and CVV from the checkout session and removes them there.
     *
     * @return array
     */
    public function takeCardData()
    {
        $session = Mage::getSingleton('checkout/session');
        $card    = $session->getPaynetEasyCard();

        $session->unsPaynetEasyCard();

        $data = $card ? json_decode(Mage::helper('core')->decrypt($card), true) : null;

        return array
        (
            'number' => is_array($data) && isset($data['number']) ? (string) $data['number'] : '',
            'cid'    => is_array($data) && isset($data['cid'])    ? (string) $data['cid']    : '',
        );
    }

    /**
     * Get PaynetEasy payment transaction object by Magento order object
     *
     * @param       MageOrder       $mageOrder          Magento order
     * @param       string          $redirectUrl        Url for final payment processing
     *
     * @return      PaynetEasy\PaynetEasyApi\PaymentData\PaymentTransaction     PaynetEasy payment transaction
     */
    protected function getPaynetTransaction(MageOrder $mageOrder, $redirectUrl = null)
    {
        $paynetTransaction = parent::getPaynetTransaction($mageOrder, $redirectUrl);

        $this->addCreditCardData($paynetTransaction, $mageOrder);

        return $paynetTransaction;
    }
}
