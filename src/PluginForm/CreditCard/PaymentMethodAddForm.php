<?php

namespace Drupal\commerce_payone\PluginForm\CreditCard;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  protected function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_payone\Plugin\Commerce\PaymentGateway\PayoneCreditCardInterface $plugin */
    $plugin = $payment->getPaymentGateway()->getPlugin();
    $plugin_configuration = $plugin->getConfiguration();

    $data = [
      'request' => 'creditcardcheck',
      'responsetype' => 'JSON',
      'mode' => $plugin_configuration['payone_mode'],
      'mid' => $plugin_configuration['payone_merchant_id'],
      'aid' => $plugin_configuration['payone_sub_account_id'],
      'portalid' => $plugin_configuration['payone_portal_id'],
      'encoding' => 'UTF-8',
      'storecarddata' => 'yes',
    ];
    $data['hash'] = $this->generateHash($data, $plugin_configuration['payone_key']);

    $element['#attached']['library'][] = 'commerce_payone/form';
    $element['#attached']['drupalSettings']['commercePayone'] = ['request' => $data];
    $element['#attributes']['class'][] = 'payone-form';
    $element['#id'] = 'payone-form';

    // Hidden elements.
    $element['payment_errors'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => ['payment-errors'],
      ],
    ];

    $element['payment_errorcode'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => ['payment-errorcode'],
      ],
    ];

    $element['pseudocardpan'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => ['pseudocardpan'],
      ],
    ];

    $element['truncatedcardpan'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => ['truncatedcardpan'],
      ],
    ];

    // Form elements.
    // Build a month select list that shows months with a leading zero.
    $months = [];
    for ($i = 1; $i < 13; $i++) {
      $month = str_pad($i, 2, '0', STR_PAD_LEFT);
      $months[$month] = $month;
    }
    // Build a year select list that uses a 4 digit key with a 2 digit value.
    $current_year_4 = date('Y');
    $current_year_2 = date('y');
    $years = [];
    for ($i = 0; $i < 10; $i++) {
      $years[$current_year_4 + $i] = $current_year_2 + $i;
    }

    $element['cardtype'] = array(
      '#type' => 'select',
      '#title' => t('Card type'),
      '#options' => array(
        'V' => t('Visa'),
        'M' => t('Mastercard'),
        'A' => t('Amex'),
        'D' => t('Diners'),
        'J' => t('JCB'),
        'O' => t('Maestro International'),
        'U' => t('Maestro UK'),
        'C' => t('Discover'),
      ),
      '#attributes' => [
        'id' => ['cardtype'],
      ],
      '#required' => TRUE,
    );

    $element['cardpan'] = array(
      '#type' => 'textfield',
      '#title' => t('Credit Card No.'),
      '#attributes' => [
        'id' => ['cardpan'],
        'autocomplete' => 'off',
      ],
      '#required' => TRUE,
      '#maxlength' => 19,
      '#size' => 20,
    );

    $element['expiration'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['credit-card-form__expiration'],
      ],
    ];
    $element['expiration']['month'] = [
      '#type' => 'select',
      '#title' => t('Month'),
      '#attributes' => [
        'id' => ['cardexpiremonth']
      ],
      '#options' => $months,
      '#default_value' => date('m'),
      '#required' => TRUE,
    ];
    $element['expiration']['divider'] = [
      '#type' => 'item',
      '#title' => '',
      '#markup' => '<span class="credit-card-form__divider">/</span>',
    ];
    $element['expiration']['year'] = [
      '#type' => 'select',
      '#title' => t('Year'),
      '#attributes' => [
        'id' => ['cardexpireyear']
      ],
      '#options' => $years,
      '#default_value' => $current_year_4,
      '#required' => TRUE,
    ];

    $element['cardcvc2'] = array(
      '#type' => 'textfield',
      '#title' => t('CVC Number'),
      '#attributes' => [
        'id' => ['cardcvc2'],
        'autocomplete' => 'off',
      ],
      '#required' => TRUE,
      '#maxlength' => 4,
      '#size' => 4,
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);

    if ($values['payment_errors'] != '') {
      // Payone Client API error messages are documented in section
      // 5.7 Error messages (Technical reference, PAYONE Platform Channel Client API).
      // Card type related error codes.
      $cardtype_codes = [1076];
      // Card number related error codes.
      $cardpan_codes = [1078];
      // Card cvc number related error codes.
      $cardcvc2_codes = [1079];
      
      if (in_array($values['payment_errorcode'], $cardtype_codes)) {
        $form_state->setError($element['cardtype'], $values['payment_errors']);
      }
      elseif (in_array($values['payment_errorcode'], $cardpan_codes)) {
        $form_state->setError($element['cardpan'], $values['payment_errors']);
      }
      elseif (in_array($values['payment_errorcode'], $cardcvc2_codes)) {
        $form_state->setError($element['cardcvc2'], $values['payment_errors']);
      }
      else {
        $form_state->setError($element, $values['payment_errors']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    // The payment gateway plugin will process the submitted payment details.
  }

  /**
   * Calculates the hash value required in Client API requests.
   *
   * @param array $data
   * @param string $securitykey
   * @return string
   */
  protected function generateHash(array $data, $securitykey) {
    // Sort by keys.
    ksort($data);

    // Hash code.
    $hashstr = '';
    foreach ($data as $key => $value) {
      $hashstr .= $data[$key];
    }
    $hashstr .= $securitykey;
    $hash = md5($hashstr);

    return $hash;
  }
}
