README file for Commerce Payone.

INTRODUCTION
------------
This module integrates the German PAYONE Payment Provider (https://www.payone.de/en/)
with Drupal Commerce 2.x (D8) to accept credit card payments on-site and PayPal
Express payments off-line. Supports also credit card payments with 3-D secure.

No external libraries required as PHP library from Payone has been marked as
"out of date" by PAYONE Technical Support.

Currently supports the following payment methods from PAYONE:
* Credit Card
* e-wallet (PayPal Express)


REQUIREMENTS
------------
This module requires the following:
* Submodules of Drupal Commerce package (https://drupal.org/project/commerce)
  - Commerce core,
  - Commerce Payment (and its dependencies);
* Payone Merchant account (https://www.payone.com/en)

INSTALLATION
-----------
This module needs to be installed either cloning the project source using git
(because sandbox modules are not packaged for download) or via Composer.

Installation via composer:
* Add repository definition to composer.json
    "repositories": {
        "commerce_payone": {
            "type": "vcs",
            "url": "https://git.drupal.org/sandbox/mitrpaka/2849906.git"
        }
    }
* Install module
composer require "drupal/commerce_payone"


CONFIGURATION
-------------
* Create new Payone payment gateway
  Administration > Commerce > Configuration > Payment gateways > Add payment gateway
  Payone-specific settings available:
  - Merchant ID
  - Portal ID
  - Sub-Account ID
  - PAYONE Key
  - Reference prefix
  Use the API credentials provided by your Payone merchant account.
  Reference prefix is for testing purposes only, to prevent duplicate reference
  errors (References needs to be unique).

* To enable 3-D Secure checking for credit card payments, please activate
  3-D Secure check from PAYONE Merchant Interface.


HOW IT WORKS
------------
* General considerations:
  - The store owner must have a Payone merchant account.
  - Customers should have a valid credit card (Credit card payments) and
    valid PayPal account (e-wallet payments).

  Payone provides several dummy credit card numbers for testing. Please
  request them from Payone Technical Support (tech.support@bspayone.com)

  For e-wallet testing, please use PayPal sandbox account
  (https://developer.paypal.com/docs/classic/lifecycle/sb_create-accounts/)

* Credit card payments:
  - Checkout workflow
    It follows the Drupal Commerce Credit Card workflow.
    The customer should enter his/her credit card data
    or select one of the credit cards saved with Payone
    from a previous order.

  - Payment Terminal
    The store owner can Void, Capture and Refund the Payone payments.

* e-wallet (PayPal Express) payments:
  - Checkout workflow
    It follows the Drupal Commerce Offline Redirect payment workflow.


TROUBLESHOOTING
---------------
* No troubleshooting pending for now.


KNOWN ISSUES
------------


MAINTAINERS
-----------
This project has been developed by:
mitrpaka@gmail.com
