<?php

namespace Drupal\commerce_payone\PluginForm\Wallet;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentOffsiteForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_payone\Plugin\Commerce\PaymentGateway\PayoneWallet $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    $form['#prefix'] = '<div id="payment-form">';
    $form['#suffix'] = '</div>';

    try {
      $order = $payment->getOrder();
      if (empty($order)) {
        throw new \InvalidArgumentException('The provided payment has no order referenced.');
      }

      // Preauthorize payment.
      $response = $payment_gateway_plugin->requestPreauthorization($order);

      if ($response->status == 'REDIRECT') {
        $redirect_url = $response->redirecturl;

        $form = $this->buildRedirectForm($form, $form_state, $redirect_url, [], 'post');
      }
      else {
        $data = [
          'status' => $response->status,
          'errorcode' => $response->errorcode,
          'errormessage' => $response->errormessage,
          'customermessage' => $response->customermessage,
        ];
        \Drupal::logger('commerce_payone')->error('Payone e-wallet preauthorization failed: %errorMsg [%errorCode]',
          [
            '%errorMsg' => $response->customermessage,
            '%errorCode' => $response->errorcode,
          ]
        );
        $redirect_url = $payment_gateway_plugin->getReturnUrl($order, 'commerce_payment.checkout.cancel');
        return $this->buildRedirectForm($form, $form_state, $redirect_url, $data, 'post');
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return $form;
  }

}
