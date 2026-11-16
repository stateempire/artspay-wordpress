/* global jQuery */

/**
 * ArtsPay secure hosted card form (Fat Zebra, tokenize only) with optional 3D Secure.
 *
 * Flow: the customer enters their card in Fat Zebra's form and presses Pay now. Fat Zebra returns a
 * card token, 3DS runs against it with fatzebra.js when enabled, then the checkout submits with
 * the token and 3DS result. The store charges the token, so card numbers never reach it.
 * Config: window.ArtsPay.hostedCard.
 */
(function ($) {
  'use strict';

  var cfg = window.ArtsPay && window.ArtsPay.hostedCard;
  if (!cfg) {
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
  var sdk = null;
  var sdkLoading = null;

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

  function parseMessage(raw) {
    if (raw && typeof raw === 'object') {
      return raw;
    }
    if (typeof raw !== 'string' || /\[object/i.test(raw)) {
      return null;
    }
    try {
      return JSON.parse(raw);
    } catch (e) {
      var out = {};
      raw.split('&').forEach(function (pair) {
        var kv = pair.split('=');
        out[decodeURIComponent(kv[0])] = decodeURIComponent(kv[1] || '');
      });
      return out;
    }
  }

  function renderSaved() {
    var $box = $('.artspay-hosted-card');
    var card = state.card || {};
    var digits = String(card.number || '').replace(/\D/g, '').slice(-4);
    var label = digits
      ? cfg.strings.saved.replace('%1$s', card.type || 'Card').replace('%2$s', digits)
      : cfg.strings.savedFallback;

    $box.find('.artspay-hosted-card__frame').attr('hidden', true).empty();
    $box.find('.artspay-hosted-card__saved').removeAttr('hidden').find('.artspay-hosted-card__saved-label').text(label);
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
    $.post(cfg.formUrl, {
      nonce: cfg.nonce,
      order_id: $box.data('order-id') || 0,
      order_key: params.get('key') || ''
    })
      .done(function (res) {
        if (!res || !res.success || !res.data || !res.data.url) {
          $frame.html('<p class="artspay-hosted-card__error"></p>').find('p').text(cfg.strings.formError);
          return;
        }
        var iframe = document.createElement('iframe');
        iframe.src = res.data.url;
        iframe.title = 'Secure card form';
        iframe.className = 'artspay-hosted-card__iframe';
        iframe.setAttribute('scrolling', 'no');
        iframe.setAttribute('allowtransparency', 'true');
        $frame.empty().append(iframe);
      })
      .fail(function () {
        $frame.html('<p class="artspay-hosted-card__error"></p>').find('p').text(cfg.strings.formError);
      })
      .always(function () {
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

  function onTokenised(msg, data) {
    var token = data.token || data.t || msg.token || msg.t || '';
    if (!token || token === state.token) {
      return;
    }
    state.token = token;
    state.card = { number: data.card_number || '', type: data.card_type || '' };
    setScaResult(null);
    renderSaved();
    submitCheckout();
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== cfg.paynowOrigin) {
      return;
    }
    var msg = parseMessage(event.data);
    if (!msg) {
      return;
    }

    var height = msg.height || msg.height_px || (msg.data && msg.data.height);
    if (height && parseInt(height, 10) > 0) {
      $('.artspay-hosted-card__iframe').css({ height: parseInt(height, 10) + 'px' });
    }

    var data = msg.data && typeof msg.data === 'object' ? msg.data : msg;
    if (msg.message === 'transaction.complete') {
      onTokenised(msg, data);
    } else if (msg.message === 'transaction.cancelled') {
      showError(cfg.strings.cancelled);
    }
  });

  function loadSdk() {
    if (sdk) {
      return $.Deferred().resolve(sdk).promise();
    }
    if (sdkLoading) {
      return sdkLoading;
    }

    var deferred = $.Deferred();
    var script = document.createElement('script');
    script.src = cfg.sdkUrl;
    script.async = true;
    script.onload = function () {
      if (typeof window.FatZebra !== 'function') {
        sdkLoading = null;
        deferred.reject();
        return;
      }
      sdk = new window.FatZebra({ username: cfg.username });
      sdk.on('fz.sca.success', function (event) {
        if (!state.verifying) {
          return;
        }
        state.verifying = false;
        setScaResult((event.detail && event.detail.data) || {});
        // WooCommerce ignores a submit while the form has the processing class.
        unblock();
        submitCheckout();
      });
      sdk.on('fz.sca.error', function () {
        if (!state.verifying) {
          return;
        }
        state.verifying = false;
        setScaResult(null);
        unblock();
        showError(cfg.strings.verifyFailed);
      });
      deferred.resolve(sdk);
    };
    script.onerror = function () {
      sdkLoading = null;
      deferred.reject();
    };
    document.head.appendChild(script);
    sdkLoading = deferred.promise();
    return sdkLoading;
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
    $.post(cfg.prepareUrl, { nonce: cfg.nonce, card_token: state.token })
      .then(function (res) {
        if (!res || !res.success) {
          return $.Deferred().reject().promise();
        }
        try {
          // fatzebra.js reads its access token from here.
          window.localStorage.setItem('fz-access-token', res.data.accessToken);
        } catch (e) {
          return $.Deferred().reject().promise();
        }
        return loadSdk().then(function (instance) {
          // Fat Zebra can take a moment to make a new card token usable (as in artspay-pay).
          var ready = $.Deferred();
          setTimeout(function () {
            ready.resolve({ instance: instance, verification: res.data.verification });
          }, 500);
          return ready.promise();
        });
      })
      .then(function (ctx) {
        // Unblocking happens in the fz.sca.* handlers or when the checkout response returns.
        var result = ctx.instance.verifyCard({
          customer: {
            firstName: field('billing_first_name'),
            lastName: field('billing_last_name'),
            email: field('billing_email'),
            address: field('billing_address_1'),
            city: field('billing_city'),
            state: field('billing_state'),
            postcode: field('billing_postcode'),
            country: field('billing_country')
          },
          paymentIntent: {
            payment: {
              amount: parseInt($box.data('total'), 10) || 0,
              currency: String($box.data('currency') || 'AUD'),
              reference: 'wc-' + Date.now()
            },
            verification: ctx.verification
          },
          paymentMethod: { type: 'card_on_file', data: { token: state.token } }
        });
        if (result && typeof result.catch === 'function') {
          result.catch(function (err) {
            window.console.error('ArtsPay: verifyCard failed', err && (err.response ? JSON.stringify(err.response.data) : err.message || err));
            if (state.verifying) {
              state.verifying = false;
              unblock();
              showError(cfg.strings.verifyError);
            }
          });
        }
      })
      .fail(function () {
        state.verifying = false;
        unblock();
        showError(cfg.strings.verifyError);
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
