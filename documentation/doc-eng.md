# PaynetEasy Payment Plugin for Magento 1.x


# 1. [Requirements](https://github.com/annihilatoratm/magento-doc/blob/main/documentation/doc-eng.md#1-requirements-1)
# 2. [Functionality](https://github.com/annihilatoratm/magento-doc/blob/main/documentation/doc-eng.md#2-functionality-1)
# 3. [Package Build](https://github.com/annihilatoratm/magento-doc/blob/main/documentation/doc-eng.md#3-package-build-1)
# 4. [Plugin Installation](https://github.com/annihilatoratm/magento-doc/blob/main/documentation/doc-eng.md#4-plugin-installation-1)
# 5. [Plugin Configuration](https://github.com/annihilatoratm/magento-doc/blob/main/documentation/doc-eng.md#5-plugin-configuration-1)
# 6. [Plugin Uninstallation](https://github.com/annihilatoratm/magento-doc/blob/main/documentation/doc-eng.md#6-plugin-uninstallation-1)
# 7. [User Registration](https://github.com/annihilatoratm/magento-doc/blob/main/documentation/doc-eng.md#7-user-registration-1)
# 8. [Payment Flow](https://github.com/annihilatoratm/magento-doc/blob/main/documentation/doc-eng.md#8-payment-flow-1)

## 1. Requirements

* PHP version: 5.3 – 5.5.
* [Curl extension](http://php.net/manual/en/book.curl.php).
* [Magento](http://www.magentocommerce.com/download) 1.x (plugin was tested with 1.7).

## 2. Functionality

This plugin allows merchant to make payments via [merchant PaynetEasy API](http://wiki.payneteasy.com/index.php/PnE:Merchant_API). These payment methods are available at the moment:
- [Sale Transactions](https://doc.payneteasy.eu/integration/api_use_cases/server_to_server_sale.html)
- [Payment Form Integration](https://doc.payneteasy.eu/integration/api_use_cases/sale_form.html)

## 3. Package Build
3.1. [Install composer](http://getcomposer.org/doc/00-intro.md), if not already installed.  
3.2. Clone the plugin repository: `composer create-project payneteasy/php-plugin-magento --stability=dev --prefer-dist`.  
3.3. Navigate to the plugin directory: `cd php-plugin-magento`.   
3.4. Archive the plugin: `composer archive --format=zip`.  

## 4. Plugin Installation

4.1. [Download the plugin package](#get_package).  
4.2. Extract the archive into the Magento root directory.  

## 5. Plugin Configuration

5.1. Log in to the Magento Admin Panel.  
5.2. Navigate to _Stores_ (1) > _Configuration_ (2).  

  <img src="/images/magento-1-2.png" width=60% height=60%>
  
5.3. Go to _Sales_ (3) > _Payment Methods_ (4).  

  <img src="/images/magento-1-1.png" width=60% height=60%>
  
5.4. Locate the Payneteasy plugin section and:  
  5.4.1. Fill in the required configuration fields.   
  5.4.2. Click **Save Config**.   

## 6. Plugin Uninstallation

To uninstall the plugin, manually remove the following files and directories. Paths are relative to the Magento root directory:

  * `app/code/local/PaynetEasy/`
  * `app/design/frontend/base/default/template/paynet/`
  * `app/etc/modules/PaynetEasy_Paynet.xml`
  * `lib/composer/`
  * `lib/payneteasy/`
  * `lib/autoload.php`
  
  ## 7. User Registration
  
To register a new user:  
  7.1. Click on **Create an Account**.  
  7.2. Fill in all required fields.  
  7.3. Click **Create an Account** to complete the registration process.    

  <img src="/images/magento-register-1.png" width=60% height=60%>
  <img src="/images/magento-register-2.png" width=60% height=60%>
  <img src="/images/magento-register-3.png" width=60% height=60%>

  ## 8. Payment Flow

  1. On the main page, click on the desired product.  
  2. Press **Add to Cart** (1-2) to add the product to your shopping cart.  
  3. Once added, click **Proceed to Checkout** (3) to begin the payment process.  

  <img src="/images/magento-1.png" width=60% height=60%>
  <img src="/images/magento-3.png" width=60% height=60%>
  
  4. In the _Shipping Details_ step, complete all required fields and click **Next**.  
  5. In the _Review & Payments_ section, choose _PAYNET_ as the payment method.  
  6. Click **Order Payment** to proceed.  

  <img src="/images/magento-4.png" width=60% height=60%>
  <img src="/images/magento-5.png" width=60% height=60%>

  3. On the _Payneteasy Payment Form_, enter all required credit card details. Click **Process Payment** and wait for the transaction to complete.  

  <img src="/images/magento-6.png" width=60% height=60%>
  <img src="/images/magento-7.png" width=60% height=60%>
