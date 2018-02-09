<?php

namespace Drupal\commerce_payone\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payone\ErrorHelper;
use Drupal\commerce_payone\PayoneApiServiceInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Off-site Redirect payment gateway for Payone e-wallet.
 *
 * @CommercePaymentGateway(
 *   id = "payone_wallet",
 *   label = "Payone e-wallet (Off-site redirect)",
 *   display_label = "Payone e-wallet",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_payone\PluginForm\Wallet\PaymentOffsiteForm",
 *   },
 * )
 */
class PayoneWallet extends OffsitePaymentGatewayBase {

  /**
   * The Stripe gateway used for making API calls.
   *
   * @var \Drupal\commerce_payone\PayoneApiServiceInterface
   */
  protected $api;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, PayoneApiServiceInterface $apiService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->api = $apiService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('commerce_payone.payment_api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'payone_mode' => 'test',
      'payone_merchant_id' => '',
      'payone_portal_id' => '',
      'payone_sub_account_id' => '',
      'payone_key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['payone_merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant ID'),
      '#default_value' => $this->configuration['payone_merchant_id'],
    ];

    $form['payone_portal_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Portal ID'),
      '#default_value' => $this->configuration['payone_portal_id'],
    ];

    $form['payone_sub_account_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sub-Account ID'),
      '#default_value' => $this->configuration['payone_sub_account_id'],
    ];

    $form['payone_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PAYONE Key'),
      '#default_value' => $this->configuration['payone_key'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['payone_mode'] = $this->getMode();
      $this->configuration['payone_merchant_id'] = $values['payone_merchant_id'];
      $this->configuration['payone_portal_id'] = $values['payone_portal_id'];
      $this->configuration['payone_sub_account_id'] = $values['payone_sub_account_id'];
      $this->configuration['payone_key'] = $values['payone_key'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // Perform the capture request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $order->getData('payone_wallet')['txid'];

    try {
      $response = $this->requestCapture($order);
      ErrorHelper::handleErrors($response);
    }
    catch (Exception $e) {
      ErrorHelper::handleException($e);
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state' => 'authorization',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $this->entityId,
      'order_id' => $order->id(),
      'test' => $this->getMode() == 'test',
      'remote_id' => $remote_id,
      'remote_state' => $request->query->get('payment_status'),
      'authorized' => $this->time->getRequestTime(),
    ]);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    if ($request->request->get('status') == 'ERROR') {
      drupal_set_message($request->request->get('customermessage'), 'error');
    }
    else {
      parent::onCancel($order, $request);
    }
  }

  /**
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   * @return mixed
   */
  public function requestPreauthorization(OrderInterface $order) {
    $customer_id = $customer_email = NULL;

    $owner = $order->getCustomer();
    if ($owner && $owner->isAuthenticated()) {
      $customer_id = $this->getRemoteCustomerId($owner);
      $customer_email = $owner->getEmail();
    }

    $request = $this->api->getClientApiStandardParameters($this->configuration, 'preauthorization');
    $request['aid'] = $this->configuration['payone_sub_account_id'];
    $request['clearingtype'] = 'wlt';
    // Reference must be unique.
    $request['reference'] = $order->id() . '_' . $this->time->getCurrentTime();
    $request['amount'] = round($order->getTotalPrice()->getNumber(), 2) * 100;
    $request['currency'] = $order->getTotalPrice()->getCurrencyCode();
    if ($customer_id) {
      $request['userid'] = $customer_id;
    }

    $request['successurl'] = $this->getReturnUrl($order, 'commerce_payment.checkout.return');
    $request['backurl'] = $this->getReturnUrl($order, 'commerce_payment.checkout.cancel');
    $request['errorurl'] = $this->getReturnUrl($order, 'commerce_payment.checkout.cancel');

    $request['hash'] = $this->api->generateHash($request, $this->configuration['payone_key']);

    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $billing_address */
    if ($billing_address = $order->getBillingProfile()) {
      $billing_address = $order->getBillingProfile()->get('address')->first();

      $request['firstname'] = $billing_address->getGivenName();
      $request['lastname'] = $billing_address->getFamilyName();
      $request['company'] = $billing_address->getOrganization();
      $request['street'] = $billing_address->getAddressLine1();
      $request['addressaddition'] = $billing_address->getAddressLine2();
      $request['zip'] = $billing_address->getPostalCode();
      $request['city'] = $billing_address->getLocality();
      $request['country'] = $billing_address->getCountryCode();
    }

    if ($customer_email) {
      $request['email'] = $customer_email;
    }

    $request['wallettype'] = 'PPE';

    $response = $this->api->processHttpPost($request);

    // Extra operations on order and owner - using gateway API.
    if ($response->status == 'REDIRECT') {
      // Save params received from API call that need to be
      // persisted until later payment creation in $order->data.
      $order->setData('payone_wallet', [
        'txid' => $response->txid,
        'userid' => $response->userid
      ]);
      $order->save();

      // Save customer information.
      $owner = $order->getCustomer();
      if ($owner && $owner->isAuthenticated()) {
        $this->setRemoteCustomerId($owner, $response->userid);
        $owner->save();
      }
    }

    return $response;
  }

  /**
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   * @param $type
   * @param string $step
   * @return \Drupal\Core\GeneratedUrl|string
   */
  public function getReturnUrl(OrderInterface $order, $type, $step = 'payment') {
    $arguments = [
      'commerce_order' => $order->id(),
      'step' => $step,
      'commerce_payment_gateway' => 'payone_wallet',
    ];
    $url = new Url($type, $arguments, [
      'absolute' => TRUE,
    ]);

    return $url->toString();
  }

  /**
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   * @return mixed
   */
  protected function requestCapture(OrderInterface $order) {
    $request = $this->api->getServerApiStandardParameters($this->configuration, 'capture');
    $request['amount'] = round($order->getTotalPrice()->getNumber(), 2) * 100;
    $request['currency'] = $order->getTotalPrice()->getCurrencyCode();
    $request['txid'] = $order->getData('payone_wallet')['txid'];

    return $this->api->processHttpPost($request, FALSE);
  }

}
