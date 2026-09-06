/**
 * Map Google Pay shipping address → WooCommerce classic checkout fields.
 */
(function ($) {
  'use strict';

  window.ArtsPay = window.ArtsPay || {};
  window.ArtsPay.checkoutAutofill = window.ArtsPay.checkoutAutofill || {};

  function splitName(fullName) {
    var name = (fullName || '').trim();
    if (!name) {
      return { first: '', last: '' };
    }
    var parts = name.split(/\s+/);
    if (parts.length === 1) {
      return { first: parts[0], last: '' };
    }
    return { first: parts.slice(0, -1).join(' '), last: parts[parts.length - 1] };
  }

  /**
   * @param {object} addr Google Pay address object
   */
  function fillWooAddressFromGooglePay(addr) {
    if (!addr) {
      return;
    }
    var name = splitName(addr.name || '');
    var country = addr.countryCode || '';
    var state = addr.administrativeArea || '';

    if (name.first) {
      $('#billing_first_name').val(name.first).trigger('change');
    }
    if (name.last) {
      $('#billing_last_name').val(name.last).trigger('change');
    }
    if (addr.phoneNumber) {
      $('#billing_phone').val(addr.phoneNumber).trigger('change');
    }
    if (addr.address1) {
      $('#billing_address_1').val(addr.address1).trigger('change');
    }
    if (addr.address2) {
      $('#billing_address_2').val(addr.address2).trigger('change');
    }
    if (addr.locality) {
      $('#billing_city').val(addr.locality).trigger('change');
    }
    if (addr.postalCode) {
      $('#billing_postcode').val(addr.postalCode).trigger('change');
    }
    if (country) {
      $('#billing_country').val(country).trigger('change');
    }
    if (state) {
      $('#billing_state').val(state).trigger('change');
    }

    var $shipDiff = $('#ship-to-different-address-checkbox');
    if ($shipDiff.length && $shipDiff.is(':checked')) {
      $shipDiff.prop('checked', false).trigger('change');
    }
    if (name.first) {
      $('#shipping_first_name').val(name.first).trigger('change');
    }
    if (name.last) {
      $('#shipping_last_name').val(name.last).trigger('change');
    }
    if (addr.address1) {
      $('#shipping_address_1').val(addr.address1).trigger('change');
    }
    if (addr.address2) {
      $('#shipping_address_2').val(addr.address2).trigger('change');
    }
    if (addr.locality) {
      $('#shipping_city').val(addr.locality).trigger('change');
    }
    if (addr.postalCode) {
      $('#shipping_postcode').val(addr.postalCode).trigger('change');
    }
    if (country) {
      $('#shipping_country').val(country).trigger('change');
    }
    if (state) {
      $('#shipping_state').val(state).trigger('change');
    }

    $(document.body).trigger('update_checkout');
  }

  window.ArtsPay.checkoutAutofill.splitName = splitName;
  window.ArtsPay.checkoutAutofill.fillWooAddressFromGooglePay = fillWooAddressFromGooglePay;
})(jQuery);
