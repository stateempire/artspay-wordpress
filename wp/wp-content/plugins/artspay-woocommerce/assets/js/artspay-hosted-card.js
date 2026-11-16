/* global jQuery */

/**
 * ArtsPay secure hosted card form on the classic checkout and pay-for-order page.
 *
 * Flow: the customer enters their card in Fat Zebra's form and presses Pay now. Fat Zebra returns a
 * card token, 3DS runs against it when enabled, then the checkout submits with the token and 3DS
 * result. The store charges the token, so card numbers never reach it.
 * Config: window.ArtsPay.hostedCard. Helpers: window.ArtsPay.fatZebra (artspay-fatzebra.js).
 */
(function ($) {
  'use strict';

  var cfg = window.ArtsPay && window.ArtsPay.hostedCard;
  var fz = window.ArtsPay && window.ArtsPay.fatZebra;
  if (!cfg || !fz) {
    return;
  }

  var state = {
    token: '',
    card: null,
    sca: null,
    total: null,
    loading: false,
    verifying: false
  };

  function getForm() {
    var $checkout = $('form.checkout');
    return $checkout.length ? $checkout : $('form#order_review');
  }

  function isSelected() {
    var $checked = $('input[name="payment_method"]:checked');
    return !$checked.length ? $('input[name="payment_method"]').val() === 'fatzebra' : $checked.val() === 'fatzebra';
  }

  function hasWalletToken() {
    return !!($('#googlepay-token').val() || $('#applepay-token').val());
  }

  function showError(message) {
    var $form = getForm();
    $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
    $form.prepend(
      '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error" role="alert"><li>' +
        $('<div>').text(message).html() +
        '</li></ul></div>'
    );
    $('html, body').animate({ scrollTop: $form.offset().top - 100 }, 400);
  }

  // Place order stays hidden until there's a card to charge: Fat Zebra's Pay now button starts it.
  function syncPlaceOrder() {
    $(document.body).toggleClass('artspay-hosted-card-pending', isSelected() && !state.token);
  }

  function setScaResult(result) {
    state.sca = result;
    $('.artspay-hosted-card__3ds').val(result ? JSON.stringify(result) : '');
  }

  function renderSaved() {
    var $box = $('.artspay-hosted-card');
    $box.find('.artspay-hosted-card__frame').attr('hidden', true).empty();
    $box.find('.artspay-hosted-card__saved').removeAttr('hidden').find('.artspay-hosted-card__saved-label').text(fz.savedLabel(cfg, state.card));
    $box.find('.artspay-hosted-card__token').val(state.token);
    $box.find('.artspay-hosted-card__3ds').val(state.sca ? JSON.stringify(state.sca) : '');
    syncPlaceOrder();
  }

  function renderFrame() {
    var $box = $('.artspay-hosted-card');
    var $frame = $box.find('.artspay-hosted-card__frame');
    if (!$box.length || state.loading || $frame.find('iframe').length) {
      return;
    }

    state.loading = true;
    $box.find('.artspay-hosted-card__saved').attr('hidden', true);
    $frame.removeAttr('hidden');

    var params = new URLSearchParams(window.location.search);
    fz.requestFormUrl(cfg, $box.data('order-id') || 0, params.get('key') || '')
      .then(function (url) {
        fz.mountFrame($frame[0], url);
      })
      .catch(function (err) {
        $frame.html('<p class="artspay-hosted-card__error"></p>').find('p').text(err.message);
      })
      .then(function () {
        state.loading = false;
      });
  }

  // Runs after every checkout refresh. WooCommerce keeps the payment section when its HTML is
  // unchanged, so this only rebuilds when the box is new (first load, total changed, method switch).
  function mount() {
    var $box = $('.artspay-hosted-card');
    syncPlaceOrder();
    if (!$box.length) {
      return;
    }

    var total = String($box.data('total'));
    if (state.total !== null && state.total !== total) {
      // A 3DS result is for one amount only.
      setScaResult(null);
    }
    state.total = total;

    if (state.token) {
      renderSaved();
    } else {
      renderFrame();
    }
  }

  function resetCard() {
    state.token = '';
    state.card = null;
    setScaResult(null);
    $('.artspay-hosted-card__token').val('');
    $('.artspay-hosted-card__frame').empty();
    renderFrame();
    syncPlaceOrder();
  }

  function submitCheckout() {
    getForm().trigger('submit');
  }

  function block() {
    getForm().addClass('processing').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
  }

  function unblock() {
    getForm().removeClass('processing').unblock();
  }

  function field(name) {
    return String($('#' + name).val() || '').trim();
  }

  function verifyCard() {
    state.verifying = true;
    block();

    var $box = $('.artspay-hosted-card');
    fz.verifyCard(cfg, {
      token: state.token,
      amount: parseInt($box.data('total'), 10) || 0,
      currency: String($box.data('currency') || 'AUD'),
      customer: {
        firstName: field('billing_first_name'),
        lastName: field('billing_last_name'),
        email: field('billing_email'),
        address: field('billing_address_1'),
        city: field('billing_city'),
        state: field('billing_state'),
        postcode: field('billing_postcode'),
        country: field('billing_country')
      }
    })
      .then(function (result) {
        state.verifying = false;
        setScaResult(result);
        // WooCommerce ignores a submit while the form has the processing class.
        unblock();
        submitCheckout();
      })
      .catch(function (err) {
        state.verifying = false;
        setScaResult(null);
        unblock();
        showError(err.message);
      });
  }

  // Returns false to stop the submit while something async (3DS) still has to happen.
  function beforePlaceOrder() {
    if (!isSelected() || hasWalletToken() || !$('.artspay-hosted-card').length) {
      return true;
    }
    if (!state.token) {
      showError(cfg.strings.enterCard);
      return false;
    }
    if (cfg.threeDs && !state.sca) {
      if (!state.verifying) {
        verifyCard();
      }
      return false;
    }
    return true;
  }

  fz.listen(cfg, {
    onHeight: function (height) {
      $('.artspay-hosted-card__iframe').css({ height: height + 'px' });
    },
    onCard: function (card) {
      if (card.token === state.token) {
        return;
      }
      state.token = card.token;
      state.card = card;
      setScaResult(null);
      renderSaved();
      submitCheckout();
    },
    onCancel: function () {
      showError(cfg.strings.cancelled);
    }
  });

  $(function () {
    $('form.checkout').on('checkout_place_order_fatzebra', beforePlaceOrder);

    // Pay-for-order submits the form normally, with no checkout_place_order event.
    $('form#order_review').on('submit', function (event) {
      if (!beforePlaceOrder()) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    });

    $(document.body).on('click', '.artspay-hosted-card__change', function (event) {
      event.preventDefault();
      resetCard();
    });

    $(document.body).on('updated_checkout payment_method_selected', mount);
    $(document.body).on('change', 'input[name="payment_method"]', mount);

    // A 3DS result covers one charge, so a failed attempt needs a new one. Field errors
    // (data-id) happen before any charge, so those keep it.
    $(document.body).on('checkout_error', function (event, message) {
      var $message = $('<div>').append($.parseHTML(String(message || '')));
      if (!$message.find('[data-id]').length) {
        setScaResult(null);
      }
      state.verifying = false;
    });

    mount();
  });
})(jQuery);
