/**
 * ArtsPay — Apple Pay express (Fat Zebra gateway).
 *
 * Uses Apple Pay JS for merchant validation + token capture, then posts token via WC checkout form.
 */
(function ($) {
  'use strict';

  var DEBUG = false;

  function log() {
    if (!DEBUG) return;
    try {
      if (window.ArtsPay && window.ArtsPay.utils && typeof window.ArtsPay.utils.log === 'function') {
        window.ArtsPay.utils.log.apply(window.ArtsPay.utils, arguments);
        return;
      }
    } catch (e) {}
    try {
      if (window.console && window.console.log) {
        window.console.log.apply(window.console, arguments);
      }
    } catch (e) {}
  }

  function errorLog() {
    if (!DEBUG) return;
    try {
      if (window.console && window.console.error) {
        window.console.error.apply(window.console, arguments);
        return;
      }
    } catch (e) {}
    log.apply(null, arguments);
  }

  function getCfg() {
    if (window.ArtsPay && window.ArtsPay.applePay) {
      return window.ArtsPay.applePay;
    }
    return null;
  }

  function isSupported() {
    return typeof window.ApplePaySession !== 'undefined';
  }

  function canPay() {
    try {
      return window.ApplePaySession && ApplePaySession.canMakePayments && ApplePaySession.canMakePayments();
    } catch (e) {
      return false;
    }
  }

  function b64UrlEncode(str) {
    try {
      return btoa(unescape(encodeURIComponent(str)))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/g, '');
    } catch (e) {
      return '';
    }
  }

  function setHiddenToken(cfg, tokenObj) {
    var json = '';
    try {
      json = JSON.stringify(tokenObj || {});
    } catch (e) {
      json = '';
    }
    var enc = b64UrlEncode(json);
    if (!enc) return;

    try {
      window.sessionStorage.setItem('artspayApplePayTokenB64', enc);
    } catch (e) {}

    var $field = $(cfg.selectors && cfg.selectors.applePayToken ? cfg.selectors.applePayToken : '#applepay-token');
    if ($field.length) {
      $field.val(enc);
    }
  }

  function ensureWalletSelected(cfg) {
    var classicSel = (cfg.selectors && cfg.selectors.paymentMethodFatzebra) || '#payment_method_fatzebra';
    var blocksSel = (cfg.blockSelectors && cfg.blockSelectors.paymentMethodFatzebra) || '';

    var $pm = $(classicSel);
    if ($pm.length) {
      $pm.prop('checked', true).trigger('change');
      return;
    }

    if (blocksSel) {
      var $pmBlocks = $(blocksSel);
      if ($pmBlocks.length) {
        $pmBlocks.prop('checked', true).trigger('change');
      }
    }
  }

  function hydrateTokenFromSessionStorage(cfg) {
    var t = null;
    try {
      t = window.sessionStorage.getItem('artspayApplePayTokenB64');
    } catch (e) {
      t = null;
    }
    if (!t) return;

    var $field = $(cfg.selectors && cfg.selectors.applePayToken ? cfg.selectors.applePayToken : '#applepay-token');
    if ($field.length) {
      $field.val(t);
      ensureWalletSelected(cfg);
    }
  }

  function postSessionRequest(cfg, validationURL) {
    return fetch(cfg.restUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        validationURL: validationURL,
        displayName: cfg.displayName,
        nonce: cfg.nonce
      })
    }).then(function (r) {
      if (!r.ok) {
        return r.text().then(function (t) {
          log('[ArtsPay][ApplePay] session failed', r.status, t);
          throw new Error('Apple Pay session failed');
        });
      }
      return r.json();
    });
  }

  function buildPaymentRequest(cfg) {
    var caps = cfg && cfg.merchantCapabilities;
    if (typeof caps === 'string') {
      caps = caps
        .split(',')
        .map(function (s) { return (s || '').trim(); })
        .filter(Boolean);
    }
    if (!Array.isArray(caps) || !caps.length) {
      // Minimum safe default required by Apple Pay JS.
      caps = ['supports3DS'];
    }

    var networks = cfg && cfg.supportedNetworks;
    if (typeof networks === 'string') {
      networks = networks
        .split(',')
        .map(function (s) { return (s || '').trim(); })
        .filter(Boolean);
    }
    if (!Array.isArray(networks) || !networks.length) {
      // Common card networks; merchants can override via localized cfg.
      networks = ['visa', 'masterCard', 'amex'];
    }

    return {
      countryCode: cfg.countryCode || 'AU',
      currencyCode: cfg.currencyCode || 'AUD',
      merchantCapabilities: caps,
      supportedNetworks: networks,
      total: {
        label: cfg.displayName || 'ArtsPay',
        amount: String(cfg.amount || '0.00')
      },
      requiredBillingContactFields: ['postalAddress', 'name', 'email'],
      requiredShippingContactFields: ['postalAddress', 'name', 'email', 'phone']
    };
  }

  function startApplePay(cfg) {
    if (!cfg || !isSupported() || !canPay()) return;

    var paymentRequest = buildPaymentRequest(cfg);
    var session = new ApplePaySession(3, paymentRequest);

    session.onvalidatemerchant = function (event) {
      postSessionRequest(cfg, event.validationURL)
        .then(function (merchantSession) {
          session.completeMerchantValidation(merchantSession);
        })
        .catch(function () {
          try { session.abort(); } catch (e) {}
        });
    };

    session.onpaymentauthorized = function (event) {
      // Fat Zebra expects the Apple Pay token object.
      setHiddenToken(cfg, event && event.payment ? event.payment.token : null);
      ensureWalletSelected(cfg);
      session.completePayment(ApplePaySession.STATUS_SUCCESS);

      // After authorization, submit checkout to create/pay the order.
      // Classic checkout: click the "Place order" button if present, else submit the form.
      // Blocks checkout: click the blocks place order button if present.
      window.setTimeout(function () {
        try {
          // Classic checkout.
          var $form = $('form.checkout');
          if ($form.length) {
            var $place = $form.find('#place_order');
            if ($place.length && !$place.prop('disabled')) {
              $place.trigger('click');
              return;
            }
            $form.trigger('submit');
            return;
          }

          // Blocks checkout (best-effort).
          var $blocksBtn = $('.wc-block-components-checkout-place-order-button, .wc-block-checkout__actions_row button[type="submit"]');
          if ($blocksBtn.length && !$blocksBtn.first().prop('disabled')) {
            $blocksBtn.first().trigger('click');
          }
        } catch (e) {}
      }, 50);
    };

    session.oncancel = function () {};

    session.begin();
  }

  function createNativeAppleButton(cfg) {
    var type = String(cfg.buttonType || 'buy');
    var style = String(cfg.buttonStyle || 'black');
    var locale = cfg.locale ? String(cfg.locale) : '';
    var el = document.createElement('apple-pay-button');
    el.setAttribute('buttonstyle', style);
    el.setAttribute('type', type);
    if (locale) {
      el.setAttribute('locale', locale);
    }
    el.setAttribute('aria-label', 'Apple Pay');
    el.addEventListener('click', function () {
      startApplePay(cfg);
    });
    return el;
  }

  function createFallbackAppleButton(cfg) {
    var $btn = $('<button/>', {
      type: 'button',
      class: 'artspay-applepay-button artspay-applepay-button--fallback',
      'aria-label': 'Apple Pay'
    });
    $btn.append(
      $('<span/>', { class: 'artspay-applepay-button__label' }).text('Apple Pay')
    );
    $btn.on('click', function () {
      startApplePay(cfg);
    });
    return $btn[0];
  }

  function mountExpressButton(cfg, $mount) {
    $mount.empty();
    if (window.customElements && window.customElements.get('apple-pay-button')) {
      $mount.append(createNativeAppleButton(cfg));
      return;
    }
    $mount.append(createFallbackAppleButton(cfg));
    if (window.customElements && typeof window.customElements.whenDefined === 'function') {
      window.customElements.whenDefined('apple-pay-button').then(function () {
        if (!$mount.length || !$mount.closest('body').length) {
          return;
        }
        $mount.empty().append(createNativeAppleButton(cfg));
      });
    }
  }

  function renderButtons(cfg) {
    if (!cfg || !cfg.expressMountSelectors) return;
    if (!isSupported() || !canPay()) return;

    var selectors = String(cfg.expressMountSelectors || '').split(',');
    selectors.forEach(function (sel) {
      sel = (sel || '').trim();
      if (!sel) return;
      var $mount = $(sel);
      if (!$mount.length) return;
      if ($mount.data('artspayApplePayMounted')) return;
      $mount.data('artspayApplePayMounted', true);

      mountExpressButton(cfg, $mount);
    });
  }

  $(function () {
    var cfg = getCfg();
    if (!cfg) return;

    // Enable verbose diagnostics only when explicitly requested.
    DEBUG = !!(cfg && cfg.debug);

    if (DEBUG) {
      // Debug helper: surface WooCommerce checkout AJAX failures (often a 400).
      $(document).on('ajaxError', function (evt, jqXHR, settings) {
        try {
          var url = settings && settings.url ? String(settings.url) : '';
          if (url.indexOf('wc-ajax=checkout') === -1) return;
          errorLog('[ArtsPay][ApplePay] checkout ajaxError', jqXHR && jqXHR.status, jqXHR && jqXHR.responseText);
        } catch (e) {}
      });

      // Classic checkout often returns HTTP 200 with a failure JSON payload.
      $(document).on('ajaxSuccess', function (evt, jqXHR, settings, data) {
        try {
          var url = settings && settings.url ? String(settings.url) : '';
          if (url.indexOf('wc-ajax=checkout') === -1) return;
          if (data && typeof data === 'object') {
            errorLog('[ArtsPay][ApplePay] checkout ajaxSuccess payload', data);
          } else if (jqXHR && typeof jqXHR.responseText === 'string') {
            errorLog('[ArtsPay][ApplePay] checkout ajaxSuccess responseText', jqXHR.responseText);
          }
        } catch (e) {}
      });

      // Woo classic checkout triggers this on checkout validation failure.
      $(document.body).on('checkout_error', function () {
        try {
          errorLog('[ArtsPay][ApplePay] checkout_error', arguments);
        } catch (e) {}
      });

      // Blocks checkout uses fetch; log failed checkout responses.
      try {
        if (window.fetch && !window.__artspayApplePayFetchWrapped) {
          window.__artspayApplePayFetchWrapped = true;
          var _fetch = window.fetch.bind(window);
          window.fetch = function (input, init) {
            var url = '';
            try {
              url = typeof input === 'string' ? input : (input && input.url ? String(input.url) : '');
            } catch (e) {
              url = '';
            }
            return _fetch(input, init).then(function (res) {
              try {
                var u = url || (res && res.url ? String(res.url) : '');
                var isCheckout =
                  (u.indexOf('wc-ajax=checkout') !== -1) ||
                  (u.indexOf('/wc/store/') !== -1 && u.indexOf('checkout') !== -1);
                if (isCheckout && res && !res.ok) {
                  return res.clone().text().then(function (txt) {
                    errorLog('[ArtsPay][ApplePay] checkout fetch failed', res.status, u, txt);
                    return res;
                  });
                }
              } catch (e) {}
              return res;
            }).catch(function (err) {
              try {
                errorLog('[ArtsPay][ApplePay] fetch error', url, err && err.message ? err.message : err);
              } catch (e) {}
              throw err;
            });
          };
        }
      } catch (e) {}
    }

    renderButtons(cfg);
    hydrateTokenFromSessionStorage(cfg);

    // Checkout refreshes can wipe hidden fields; rehydrate after Woo updates.
    $(document.body).on('updated_checkout updated_cart_totals', function () {
      renderButtons(cfg);
      hydrateTokenFromSessionStorage(cfg);
    });
  });
})(jQuery);

