/**
 * ArtsPay — Google Pay express (Fat Zebra gateway).
 */
(function ($) {
  'use strict';

  var googlePayClient = null;

  function getCfg() {
    if (window.ArtsPay && window.ArtsPay.googlePay) {
      return window.ArtsPay.googlePay;
    }
    return typeof fzgooglepay !== 'undefined' ? fzgooglepay : null;
  }

  function ensureClient(cfg) {
    if (!cfg || typeof google === 'undefined' || !google.payments || !google.payments.api) {
      return null;
    }
    if (!googlePayClient) {
      googlePayClient = new google.payments.api.PaymentsClient({
        environment: cfg.environment
      });
    }
    return googlePayClient;
  }

  function baseCardPaymentMethod(cfg) {
    return {
      type: 'CARD',
      parameters: {
        allowedCardNetworks: cfg.allowedCardNetworks || ['VISA', 'MASTERCARD', 'AMEX', 'JCB'],
        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS']
      }
    };
  }

  function buildPaymentDataRequest(cfg) {
    var tokenizationSpecification = {
      type: 'PAYMENT_GATEWAY',
      parameters: {
        gateway: 'fatzebra',
        gatewayMerchantId: cfg.gatewayMerchantId
      }
    };

    var cardPaymentMethod = {
      type: 'CARD',
      tokenizationSpecification: tokenizationSpecification,
      parameters: {
        allowedCardNetworks: cfg.allowedCardNetworks || ['VISA', 'MASTERCARD', 'AMEX', 'JCB'],
        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
        billingAddressRequired: false
      }
    };

    var allowedCountries =
      cfg && cfg.allowedCountryCodes && cfg.allowedCountryCodes.length
        ? cfg.allowedCountryCodes
        : undefined;

    return {
      apiVersion: 2,
      apiVersionMinor: 0,
      allowedPaymentMethods: [cardPaymentMethod],
      emailRequired: true,
      shippingAddressRequired: true,
      shippingAddressParameters: allowedCountries
        ? { allowedCountryCodes: allowedCountries, phoneNumberRequired: true }
        : { phoneNumberRequired: true },
      transactionInfo: {
        totalPriceStatus: 'FINAL',
        totalPrice: String(cfg.amount),
        currencyCode: cfg.currencyCode
      },
      merchantInfo: (function () {
        var mi = {
          merchantName: cfg.googleMerchantName,
          merchantOrigin: typeof window !== 'undefined' ? window.location.origin : ''
        };
        if (cfg.googleMerchantId && String(cfg.googleMerchantId).trim() !== '') {
          mi.merchantId = String(cfg.googleMerchantId).trim();
        }
        return mi;
      })()
    };
  }

  function runGooglePaySheet(cfg) {
    if (!cfg || !cfg.gatewayMerchantId || String(cfg.gatewayMerchantId).trim() === '') {
      window.alert(
        cfg && cfg.missingGatewayMerchantMessage
          ? cfg.missingGatewayMerchantMessage
          : 'Google Pay is not configured.'
      );
      return;
    }

    var client = ensureClient(cfg);
    if (!client) {
      window.alert(
        'Google Pay could not load. Use HTTPS in production, or try another browser. Extensions that block pay.google.com will also prevent Google Pay.'
      );
      return;
    }

    var request = buildPaymentDataRequest(cfg);
    client
      .loadPaymentData(request)
      .then(function (paymentData) {
        onGooglePaySuccess(cfg, paymentData);
      })
      .catch(function (err) {
        if (window.ArtsPay && window.ArtsPay.utils && window.ArtsPay.utils.log) {
          window.ArtsPay.utils.log('ArtsPay Google Pay: loadPaymentData', err);
        } else {
          window.console.log('ArtsPay Google Pay: loadPaymentData', err);
        }
      });
  }

  function selectFatzebra(cfg) {
    var s = (cfg.selectors && cfg.selectors.paymentMethodFatzebra) || '#payment_method_fatzebra';
    var $r = $(s);
    if ($r.length) {
      $r.prop('checked', true).trigger('change');
      return;
    }
    if (cfg.blockSelectors && cfg.blockSelectors.paymentMethodFatzebra) {
      $r = $(cfg.blockSelectors.paymentMethodFatzebra).first();
      if ($r.length) {
        $r.prop('checked', true).trigger('change');
      }
    }
  }

  function setToken(encoded) {
    var $t = $('#googlepay-token');
    if ($t.length) {
      $t.val(encoded);
    }
  }

  function onGooglePaySuccess(cfg, paymentData) {
    var paymentToken = paymentData.paymentMethodData.tokenizationData.token;
    var encoded = window.btoa(paymentToken);
    var shipping = paymentData.shippingAddress || null;
    var email = paymentData.email || '';
    var str = (cfg.strings && cfg.strings.googlePayAuthorised) || '';

    if (cfg.isCart) {
      try {
        window.sessionStorage.setItem('artspayGooglePayTokenB64', encoded);
        window.sessionStorage.setItem('artspayGooglePayEmail', email || '');
        if (shipping) {
          window.sessionStorage.setItem('artspayGooglePayShipping', JSON.stringify(shipping));
        }
      } catch (e) {
        window.console.warn('ArtsPay Google Pay: sessionStorage unavailable', e);
      }
      if (cfg.checkoutUrl) {
        window.location.href = cfg.checkoutUrl;
      }
      return;
    }

    if (cfg.isBlocksCheckout) {
      try {
        window.sessionStorage.setItem('artspayGooglePayTokenB64', encoded);
        window.sessionStorage.setItem('artspayGooglePayEmail', email || '');
        if (shipping) {
          window.sessionStorage.setItem('artspayGooglePayShipping', JSON.stringify(shipping));
        }
      } catch (e) {
        window.console.warn('ArtsPay Google Pay: sessionStorage unavailable', e);
      }
      setToken(encoded);
      selectFatzebra(cfg);
      if (email) {
        $('#billing_email').val(email).trigger('change');
      }
      if (shipping && window.ArtsPay && window.ArtsPay.checkoutAutofill) {
        window.ArtsPay.checkoutAutofill.fillWooAddressFromGooglePay(shipping);
      }
      var $host = $('.artspay-express-checkout--blocks').first();
      if ($host.length && str) {
        $host.find('.artspay-googlepay-postauth').remove();
        $host.append(
          '<div class="woocommerce-info artspay-googlepay-postauth" role="status">' + str + '</div>'
        );
      }
      $(document.body).trigger('update_checkout');
      return;
    }

    if (cfg.isCheckout) {
      setToken(encoded);
      selectFatzebra(cfg);

      try {
        if (email) {
          $('#billing_email').val(email).trigger('change');
        }
        if (shipping && window.ArtsPay && window.ArtsPay.checkoutAutofill) {
          window.ArtsPay.checkoutAutofill.fillWooAddressFromGooglePay(shipping);
        }
      } catch (e) {
        window.console.warn('ArtsPay Google Pay: autofill failed', e);
      }

      var $host = $(cfg.selectors && cfg.selectors.expressHostCheckout ? cfg.selectors.expressHostCheckout : '.artspay-express-checkout--checkout').first();
      if ($host.length && str) {
        $host.find('.artspay-googlepay-postauth').remove();
        $host.append(
          '<div class="woocommerce-info artspay-googlepay-postauth" role="status">' + str + '</div>'
        );
      }

      var $billing = $('#customer_details, #billing_first_name').first();
      if ($billing.length) {
        $('html, body').animate({ scrollTop: $billing.offset().top - 60 }, 350);
      }

      var $form = $('form.checkout');
      var $placeOrder = $('#place_order');
      if ($form.length && $placeOrder.length) {
        var requiredEmpty = false;
        $form.find('.validate-required :input:visible').each(function () {
          var $input = $(this);
          var name = ($input.attr('name') || '').toLowerCase();
          if (name.indexOf('billing_') === 0 || name.indexOf('shipping_') === 0) {
            if (!$input.val()) {
              requiredEmpty = true;
            }
          }
        });

        var $terms = $('#terms');
        var termsOk = !$terms.length || $terms.is(':checked');

        if (!requiredEmpty && termsOk) {
          window.setTimeout(function () {
            $placeOrder.trigger('click');
          }, 150);
        }
      }
    }
  }

  window.onGooglePaymentsButtonClicked = function () {
    runGooglePaySheet(getCfg());
  };

  function mountExpressButtons(cfg) {
    var client = ensureClient(cfg);
    if (!client) {
      return;
    }

    var selectors = (cfg.expressMountSelectors || '')
      .split(',')
      .map(function (s) {
        return s.trim();
      })
      .filter(Boolean);

    if (!selectors.length) {
      return;
    }

    var base = {
      apiVersion: 2,
      apiVersionMinor: 0,
      allowedPaymentMethods: [baseCardPaymentMethod(cfg)]
    };

    selectors.forEach(function (sel) {
      var host = document.querySelector(sel);
      if (host) {
        host.innerHTML = '';
      }
    });

    client
      .isReadyToPay(base)
      .then(function (response) {
        if (!response.result) {
          window.console.error(
            'ArtsPay Google Pay: isReadyToPay is false (HTTPS, supported browser, and a Google account with a card are required).'
          );
          return;
        }

        selectors.forEach(function (sel) {
          var host = document.querySelector(sel);
          if (!host) {
            return;
          }
          while (host.firstChild) {
            host.removeChild(host.firstChild);
          }

          var btn = client.createButton({
            buttonType: cfg && cfg.buttonType ? cfg.buttonType : 'buy',
            buttonColor: cfg && cfg.buttonColor ? cfg.buttonColor : 'default',
            buttonSizeMode: 'fill',
            onClick: function () {
              runGooglePaySheet(cfg);
            }
          });
          host.appendChild(btn);
        });
      })
      .catch(function (err) {
        window.console.error('ArtsPay Google Pay: isReadyToPay error', err);
      });
  }

  function applyPrefillFromCart() {
    var cfg = getCfg();
    if (!cfg || (!cfg.isCheckout && !cfg.isBlocksCheckout)) {
      return;
    }
    try {
      var t = window.sessionStorage.getItem('artspayGooglePayTokenB64');
      if (t) {
        setToken(t);
        window.sessionStorage.removeItem('artspayGooglePayTokenB64');
        var email = window.sessionStorage.getItem('artspayGooglePayEmail');
        var ship = window.sessionStorage.getItem('artspayGooglePayShipping');
        if (email) {
          $('#billing_email').val(email).trigger('change');
          window.sessionStorage.removeItem('artspayGooglePayEmail');
        }
        if (ship) {
          try {
            if (window.ArtsPay && window.ArtsPay.checkoutAutofill) {
              window.ArtsPay.checkoutAutofill.fillWooAddressFromGooglePay(JSON.parse(ship));
            }
          } catch (e) {
            window.console.warn('ArtsPay Google Pay: shipping parse failed', e);
          }
          window.sessionStorage.removeItem('artspayGooglePayShipping');
        }
        selectFatzebra(cfg);
      }
    } catch (e) {
      window.console.warn('ArtsPay Google Pay: prefill', e);
    }
  }

  function boot() {
    var cfg = getCfg();
    if (!cfg) {
      return;
    }

    googlePayClient = null;

    applyPrefillFromCart();

    mountExpressButtons(cfg);
  }

  $(function () {
    boot();
    $(document.body).on('updated_checkout', function () {
      boot();
    });
  });
})(jQuery);
