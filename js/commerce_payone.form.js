/**
 * @file
 * Javascript to generate Payone Pseudo-PAN token in PCI-compliant way.
 */

(function ($, Drupal, drupalSettings, payone) {
  Drupal.behaviors.commercePayoneForm = {
    attach: function (context) {
      if (typeof drupalSettings.commercePayone.fetched == 'undefined') {
        drupalSettings.commercePayone.fetched = true;

        var $form = $('.payone-form', context).closest('form');

        var data = drupalSettings.commercePayone.request;

        var options = {
          return_type : 'object',
          callback_function_name: 'processPayoneResponse'
        };

        $form.submit(function (e) {
          $.extend(data, {
            cardtype: document.getElementById('cardtype').value,
            cardpan: document.getElementById('cardpan').value,
            cardexpiremonth: document.getElementById('cardexpiremonth').value,
            cardexpireyear: document.getElementById('cardexpireyear').value,
            cardcvc2: document.getElementById('cardcvc2').value
          });

          var request = new PayoneRequest(data, options);
          request.checkAndStore();

          var $form = $(this);
          // Disable the submit button to prevent repeated clicks
          $form.find('button').prop('disabled', true);
          // Prevent the form from submitting with the default action
          return false;
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);

// Outside of Drupal behaviours because ajax.js calls callback using global context.
function processPayoneResponse(response) {
  var form = document.getElementById('pseudocardpan');
  while (form.nodeName != "FORM" && form.parentNode) {
    form = form.parentNode;
  }

  if (response.get('status') == 'VALID') {
    document.getElementById('payment-errors').value = '';
    document.getElementById('pseudocardpan').value = response.get('pseudocardpan');
  }
  else {
    // Show the errors on the form
    document.getElementById('payment-errors').value = response.get('customermessage');
    document.getElementById('payment-errorcode').value = response.get('errorcode');
  }
  form.submit();
}
