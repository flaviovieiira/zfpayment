<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Mage
 * @package    Mage_Cybermut
 * @copyright  Copyright (c) 2004-2007 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Cybermut Payment Model
 *
 * @category   Mage
 * @package    Mage_Cybermut
 * @name       Mage_Cybermut_Model_Payment
 * @author	   Magento Core Team <core@magentocommerce.com>, Quadra Informatique - Nicolas Fischer <nicolas.fischer@quadra-informatique.fr>
 */

class Mage_Cybermut_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'cybermut_payment';
    protected $_formBlockType = 'cybermut/form';

    // Cybermut return codes of payment
    const RETURN_CODE_ACCEPTED      = 'paiement';
    const RETURN_CODE_TEST_ACCEPTED = 'payetest';
    const RETURN_CODE_ERROR         = 'Annulation';

    // Payment configuration
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;

    // Order instance
    protected $_order = null;

    /**
     *  Return CyberMut protocol version
     *
     *  @param    none
     *  @return	  string Protocol version
     */
    protected function getVersion()
    {
        return '1.2open';
    }

    /**
     *  Returns Target URL
     *
     *  @return	  string Target URL
     */
    public function getCybermutUrl()
    {
        $url = '';
        switch ($this->getConfigData('bank'))
        {
            default:
            case 'mutuel':
                $url = $this->getConfigData('test_mode')
                    ? 'https://paiement.creditmutuel.fr/test/paiement.cgi'
                    : 'https://paiement.creditmutuel.fr/paiement.cgi';
                break;
            case 'cic':
                $url = $this->getConfigData('test_mode')
                    ? 'https://ssl.paiement.cic-banques.fr/test/paiement.cgi'
                    : 'https://ssl.paiement.cic-banques.fr/paiement.cgi';
                break;
            case 'obc':
                $url = $this->getConfigData('test_mode')
                    ? 'https://ssl.paiement.banque-obc.fr/test/paiement.cgi'
                    : 'https://ssl.paiement.banque-obc.fr/paiement.cgi';
                break;
        }
        return $url;
    }

    /**
     *  Return back URL
     *
     *  @return	  string URL
     */
	protected function getReturnURL()
	{
		return $this->getErrorURL();
	}

	/**
	 *  Return URL for Cybermut success response
	 *
	 *  @return	  string URL
	 */
	protected function getSuccessURL()
	{
		return Mage::getUrl('cybermut/payment/success', array('_secure' => true));
	}

    /**
     *  Return URL for Cybermut failure response
     *
     *  @return	  string URL
     */
    protected function getErrorURL()
    {
        return Mage::getUrl('cybermut/payment/error', array('_secure' => true));
    }

	/**
	 *  Return URL for Cybermut notify response
	 *
	 *  @return	  string URL
	 */
	protected function getNotifyURL()
	{
		return Mage::getUrl('cybermut/payment/notify', array('_secure' => true));
	}

    /**
     * Capture payment
     *
     * @param   Varien_Object $orderPayment
     * @return  Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
		$payment->setStatus(self::STATUS_APPROVED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

	/**
	 * Validate payment method information object
	 *
	 * @param   Varien_Object $info
	 * @return  Mage_Payment_Model_Abstract
	 */
	public function validate()
	{
		$quote = Mage::getSingleton('checkout/session')->getQuote();
		$quote->setCustomerNoteNotify(false);
		parent::validate();
    }

    /**
     *  Form block description
     *
     *  @return	 object
     */
    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('cybermut/form_payment', $name);
        $block->setMethod($this->_code);
        $block->setPayment($this->getPayment());

        return $block;
    }

    /**
     *  Return Order Place Redirect URL
     *
     *  @return	  string Order Redirect URL
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('cybermut/payment/redirect');
    }

    /**
     *  Return Standard Checkout Form Fields for request to Cybermut
     *
     *  @return	  array Array of hidden form fields
     */
    public function getStandardCheckoutFormFields()
    {
		$session = Mage::getSingleton('checkout/session');
        
        $order = $this->getOrder();
        if (!($order instanceof Mage_Sales_Model_Order)) {
            Mage::throwException($this->_getHelper()->__('Cannot retrieve order object'));
        }

		$description = $this->getConfigData('description')
            ? $this->getConfigData('description')
            : Mage::helper('cybermut')->__('Order %s', $order->getRealOrderId());
		
		$fields = array(
                        'version'        => $this->getVersion(),
                        'TPE'            => $this->getConfigData('tpe_no'),
                        'date'           => date('d/m/Y:H:i:s'),
                        'montant'        => sprintf('%.2f', $order->getBaseGrandTotal()) . $order->getBaseCurrencyCode(),
                        'reference'      => $order->getRealOrderId(),
                        'texte-libre'    => $description,
                        'lgue'           => $this->_getLanguageCode(),
                        'societe'        => $this->getConfigData('site_code'),
                        'url_retour'     => $this->getReturnURL(),
                        'url_retour_ok'  => $this->getSuccessURL(),
                        'url_retour_err' => $this->getErrorURL(),
                        'bouton'         => 'ButtonLabel'
                        );

        $fields['MAC'] = $this->_getMAC($fields);

        return $fields;
    }

	/**
	 *  Return vars to pur in the URL
	 *  Fabrication des donn�es GET au cas o� la transmission Cybermut -> serveur Magento n'a pas fonctionn�e
	 *
	 *  @param	  array
	 *  @return	  String
	 */
	protected function _getUrlVars($code_retour)
	{
		$order = $this->getOrder();
		$session = Mage::getSingleton('checkout/session');

		$description = $this->getConfigData('description')
            ? $this->getConfigData('description')
            : Mage::helper('cybermut')->__('Order %s', $order->getRealOrderId());

		$get['retourPLUS'] = "--no-option";
		$get['TPE'] = $this->getConfigData('tpe_no');
		$get['date'] = date('d/m/Y:H:i:s');
		$get['montant'] = sprintf('%.2f', $order->getBaseGrandTotal()) . $order->getBaseCurrencyCode();
		$get['reference'] = $order->getRealOrderId();
		$get['texte-libre'] = $description;
		$get['code-retour'] = $code_retour;
		
		return "?MAC=" . $this->getResponseMAC($get)
				. "&TPE=" . $this->getConfigData('tpe_no')
				."&date=" . date('d/m/Y:H:i:s')
				."&montant=" . sprintf('%.2f', $order->getBaseGrandTotal()) . $order->getBaseCurrencyCode()
				."&reference=" . $order->getRealOrderId()
				."&texte-libre=" . $description
				."&code-retour=" . $code_retour
				."&retourPLUS=--no-option";
	}

    /**
     *  Prepare string for MAC generation
     *
     *  @param    none
     *  @return	  string MAC string
     */
    protected function _getMAC($data)
    {
        $string =  sprintf('%s*%s*%s*%s*%s*%s*%s*%s*',
            $data['TPE'],
            $data['date'],
            $data['montant'],
            $data['reference'],
            $data['texte-libre'],
            $data['version'],
            $data['lgue'],
            $data['societe']
        );

        return $this->_CMCIC_hmac($string);
    }

    /**
     *  Return SHA key
     *
     *  @param    none
     *  @return	  string SHA key
     */
    protected function _getSHAKey()
    {
        return $this->getConfigData('sha_key');
    }

    /**
     *  Return merchant key
     *
     *  @param    none
     *  @return	  string Merchant key
     */
    protected function _getKey()
    {
        return $this->getConfigData('key');
    }
    
    /**
     *  Return encrypted key
     *
     *  @param    none
     *  @return	  string encrypted key
     */
    protected function _getKeyEncrypted()
    {
        $key = $this->getConfigData('key_encrypted');
		$key = Mage::helper('core')->decrypt($key);
		
		$avant_dernier_tranforme = (ord(substr($key, strlen($key)-2, 1)) - 23);
		$key = substr($key, 0, strlen($key)-2) . chr($avant_dernier_tranforme) . substr($key, strlen($key)-1, 1);
		
		return pack("H*", $key);
    }

	/**
	 *  Select old and new system
	 *
	 *  @param    string
	 *  @return	  string encrypted key
	 */    
    protected function _CMCIC_hmac($string)
    {
		if ($this->getConfigData('key_encrypted')) {
			return $this->_CMCIC_hmac_KeyEncrypted($string);
		} else {
			return $this->_CMCIC_hmac_KeyPassphrase($string);
		}
    }
    
	/**
	 * Return MAC string for payment authentification 
	 * new system
	 *
	 *  @param    string
	 *  @return	  string encrypted key
	 */
    protected function _CMCIC_hmac_KeyEncrypted($string) {
    	$key = $this->_getKeyEncrypted();

        $length = 64; // block length for SHA1
        if (strlen($key) > $length) {
            $key = pack("H*",sha1($key));
        }

        $key  = str_pad($key, $length, chr(0x00));
        $ipad = str_pad('', $length, chr(0x36));
        $opad = str_pad('', $length, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;

        return sha1($k_opad  . pack("H*",sha1($k_ipad . $string)));
    }
    
	/**
	 * Return MAC string for payment authentification
	 * old HMAC system
	 *
	 *  @param    string
	 *  @return	  string encrypted key
	 */
    protected function _CMCIC_hmac_KeyPassphrase($string) {
    	$pass = $this->_getSHAKey();
        $k1 = pack("H*",sha1($this->_getSHAKey()));
        $l1 = strlen($k1);
        $k2 = pack("H*", $this->_getKey());
        $l2 = strlen($k2);
        if ($l1 > $l2) {
            $k2 = str_pad($k2, $l1, chr(0x00));
        } elseif ($l2 > $l1) {
            $k1 = str_pad($k1, $l2, chr(0x00));
        }
        
        return strtolower($this->_hmacSHA1($k1 ^ $k2, $string));
    }
    
	/**
	 *  Old HMAC SHA system
	 *
	 *  @param    string
	 *  @return	  string encrypted key
	 */
    protected function _hmacSHA1($key, $string)
    {
        $length = 64; // block length for SHA1
        if (strlen($key) > $length) {
            $key = pack("H*",sha1($key));
        }

        $key  = str_pad($key, $length, chr(0x00));
        $ipad = str_pad('', $length, chr(0x36));
        $opad = str_pad('', $length, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;
        
        return sha1($k_opad  . pack("H*",sha1($k_ipad . $string)));
    }

	/**
     *  Return MAC string for payment authentification
     *
     *  @param    none
     *  @return	  string MAC
     */
    /* <FONCTION OBSOLETE>
    protected function _CMCIC_hmac($string)
    {
        $pass = $this->_getSHAKey();
        $k1 = pack("H*",sha1($this->_getSHAKey()));
        $l1 = strlen($k1);
        $k2 = pack("H*", $this->_getKey());
        $l2 = strlen($k2);
        if ($l1 > $l2) {
            $k2 = str_pad($k2, $l1, chr(0x00));
        } elseif ($l2 > $l1) {
            $k1 = str_pad($k1, $l2, chr(0x00));
        }
        
        return strtolower($this->_hmacSHA1($k1 ^ $k2, $string));
    }
    
    <FONCTION OBSOLETE> */ 

    /**
     *  MAC generation algorithm
     *
     *  @param    none
     *  @return	  string MAC
     */
    /* <FONCTION OBSOLETE>
    protected function _hmacSHA1($key, $string)
    {
        $length = 64; // block length for SHA1
        if (strlen($key) > $length) {
            $key = pack("H*",sha1($key));
        }

        $key  = str_pad($key, $length, chr(0x00));
        $ipad = str_pad('', $length, chr(0x36));
        $opad = str_pad('', $length, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;
        
        return sha1($k_opad  . pack("H*",sha1($k_ipad . $string)));
    }
    
	</FONCTIONS OBSOLETES> */
    
	/**
	 * Return authorized languages by CyberMUT
	 *
	 * @param	none
	 * @return	array
	 */
	protected function _getAuthorizedLanguages()
	{
		$languages = array();
		
        foreach (Mage::getConfig()->getNode('global/payment/cybermut_payment/languages')->asArray() as $data) 
		{
			$languages[$data['code']] = $data['name'];
		}
		
		return $languages;
	}
	
	/**
	 * Return language code to send to CyberMUT
	 *
	 * @param	none
	 * @return	String
	 */
	protected function _getLanguageCode()
	{
		// Store language
		$language = strtoupper(substr(Mage::getStoreConfig('general/locale/code'), 0, 2));

		// Authorized Languages
		$authorized_languages = $this->_getAuthorizedLanguages();

		if (count($authorized_languages) === 1) 
		{
			$codes = array_keys($authorized_languages);
			return $codes[0];
		}
		
		if (array_key_exists($language, $authorized_languages)) 
		{
			return $language;
		}
		
		// By default we use language selected in store admin
		return $this->getConfigData('language');
	}

    /**
     *  Return MAC string on basis of Cybermut response data
     *
     *  @param    none
     *  @return	  string MAC
     */
    public function getResponseMAC($data)
    {
        $string = sprintf('%s%s+%s+%s+%s+%s+%s+%s+',
             $data['retourPLUS'],
             $data['TPE'],
             $data['date'],
             $data['montant'],
             $data['reference'],
             $data['texte-libre'],
             $this->getVersion(),
             $data['code-retour']
        );
        
        return strtoupper($this->_CMCIC_hmac($string));
    }

    /**
     *  Transaction successful or not
     *
     *  @param    none
     *  @return	  boolean
     */
    public function isSuccessfulPayment($returnCode)
    {
        return in_array($returnCode, array(self::RETURN_CODE_ACCEPTED, self::RETURN_CODE_TEST_ACCEPTED));
    }

    /**
     *  Output success response and stop the script
     *
     *  @param    none
     *  @return	  void
     */
    public function generateSuccessResponse()
    {
        die($this->getSuccessResponse());
    }

    /**
     *  Output failure response and stop the script
     *
     *  @param    none
     *  @return	  void
     */
    public function generateErrorResponse()
    {
        die($this->getErrorResponse());
    }

    /**
     *  Return response for Cybermut success payment
     *
     *  @param    none
     *  @return	  string Success response string
     */
    public function getSuccessResponse()
    {
        $response = array(
            'Pragma: no-cache',
            'Content-type : text/plain',
            'Version: 1',
            'OK'
        );
        return implode("\n", $response) . "\n";
    }

    /**
     *  Return response for Cybermut failure payment
     *
     *  @param    none
     *  @return	  string Failure response string
     */
    public function getErrorResponse()
    {
        $response = array(
            'Pragma: no-cache',
            'Content-type : text/plain',
            'Version: 1',
            'Document falsifie'
        );
        return implode("\n", $response) . "\n";
    }

}