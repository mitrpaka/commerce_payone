<?php

namespace Drupal\commerce_payone\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\Core\Access\AccessException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides endpoints for credit card payments, if 3-D Secure check enabled for
 * the shop in Payone Merchant Interface.
 */
class SecureCheckController {

  /**
   * Provides the "return" checkout payment page for 3-D Secure check.
   *
   * Redirects to the next checkout page, completing checkout.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function returnCheckoutPage(OrderInterface $commerce_order, Request $request) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $commerce_order->payment_gateway->entity;
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if (!$payment_gateway_plugin instanceof OnsitePaymentGatewayInterface) {
      throw new AccessException('The payment gateway for the order does not implement ' . OnsitePaymentGatewayInterface::class);
    }

    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $commerce_order->checkout_flow->entity;
    $checkout_flow_plugin = $checkout_flow->getPlugin();
    $checkout_flow_plugin_configuration = $checkout_flow_plugin->getConfiguration();
    $capture = $checkout_flow_plugin_configuration['panes']['payment_process']['capture'];

    try {
      $payment_gateway_plugin->onSecurityCheckReturn($commerce_order, $capture);
      $redirect_step = $checkout_flow_plugin->getNextStepId();
    }
    catch (PaymentGatewayException $e) {
      \Drupal::logger('commerce_payone')->error($e->getMessage());
      drupal_set_message(t('Payment failed at the payment server. Please review your information and try again.'), 'error');
      $redirect_step = $checkout_flow_plugin->getPreviousStepId();
    }

    $checkout_flow_plugin->redirectToStep($redirect_step);
  }

  /**
   * Provides the "cancel" checkout payment page for 3-D Secure check.
   *
   * Redirects to the previous checkout page.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function cancelCheckoutPage(OrderInterface $commerce_order, Request $request) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $commerce_order->payment_gateway->entity;
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if (!$payment_gateway_plugin instanceof OnsitePaymentGatewayInterface) {
      throw new AccessException('The payment gateway for the order does not implement ' . OnsitePaymentGatewayInterface::class);
    }

    $payment_gateway_plugin->onSecurityCheckCancel($commerce_order);
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $commerce_order->checkout_flow->entity;
    /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow_plugin */
    $checkout_flow_plugin = $checkout_flow->getPlugin();
    $previous_step_id = $checkout_flow_plugin->getPreviousStepId();
    foreach ($checkout_flow_plugin->getPanes() as $pane) {
      if ($pane->getId() == 'payment_information') {
        $previous_step_id = $pane->getStepId();
      }
    }
    $checkout_flow_plugin->redirectToStep($previous_step_id);
  }

}
