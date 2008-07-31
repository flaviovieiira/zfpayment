<?php
 
/**
* Our test CC module adapter
*/
class Mage_Servired_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'servired';
    
    //protected $_formBlockType = 'payment/form_checkmo';
    //protected $_infoBlockType = 'payment/info_cod';

	protected $_allowCurrencyCode = array('EUR');


    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Method_Checkmo
     */
    public function isAvailable($quote=null)
	    {
	        $shipping = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod(); 

	        if (eregi("freeshipping_freeshipping", $shipping)) {
	            return true;
	        }
	        return false;
	    }

     /**
     * Get paypal session namespace
     *
     * @return Mage_Paypal_Model_Session
     */
    public function getSession()
    {
        return Mage::getSingleton('paypal/session');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Using internal pages for input payment data
     *
     * @return bool
     */
    public function canUseInternal()
    {
        return false;
    }

    /**
     * Using for multiple shipping address
     *
     * @return bool
     */
    public function canUseForMultishipping()
    {
        return false;
    }

    public function getUrl()
    {
         $url = Mage::getStoreConfig('servired/url');
         return $url;
    }
    
    public function getDebug()
    {
        return Mage::getStoreConfig('servired/debug');
    }  
    

}