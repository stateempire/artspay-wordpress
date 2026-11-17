/**
 * ArtsPay on the block Cart and Checkout.
 *
 * Card payment method: Fat Zebra's secure hosted form (with optional 3D Secure) or on-site card
 * fields, plus captcha and the Forter device ID. Express payment methods: Google Pay and Apple Pay.
 * Everything is charged by the gateway's process_payment(), which reads the payment method data
 * set here. Plain JavaScript on WordPress and WooCommerce globals, so there is no build step.
 * Config: getSetting( 'fatzebra_data' ) from Blocks\CardMethod.
 */
(function () {
  'use strict';

  var wc = window.wc || {};
  var registry = wc.wcBlocksRegistry;
  var settings = wc.wcSettings;
  if (!registry || !settings || !window.wp || !window.wp.element) {
    return;
  }

  var el = window.wp.element;
  var h = el.createElement;
  var useEffect = el.useEffect;
  var useRef = el.useRef;
  var useState = el.useState;
  var decode = window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities : function (s) { return s; };
  var data = settings.getSetting('fatzebra_data', {});
  var fz = window.ArtsPay && window.ArtsPay.fatZebra;
  var strings = data.strings || {};

  if (!data.title) {
    return;
  }

  // Shorten WooCommerce's divider under the express buttons from "Or continue below" to "Or".
  if (window.wp.hooks) {
    window.wp.hooks.addFilter('i18n.gettext_woocommerce', 'artspay/express-divider', function (translation, text) {
      return text === 'Or continue below' ? strings.expressDivider || 'Or' : translation;
    });
  }

  // When a wallet payment started submitting. The card method stays mounted underneath, so its
  // payment setup must step aside rather than ask for a card. Cleared when the checkout finishes;
  // the time limit covers a wallet submit stopped by checkout validation, which fires neither.
  var walletPendingSince = 0;

  function walletPending() {
    return Date.now() - walletPendingSince < 60000;
  }

  // Payment setup observers run for every payment. Returning true means "nothing to add".
  var NOTHING = true;

  function amountString(billing) {
    var minor = billing.currency.minorUnit;
    return (billing.cartTotal.value / Math.pow(10, minor)).toFixed(minor);
  }

  function setCheckoutAddresses(billing, shipping) {
    var cart = window.wp.data && window.wp.data.dispatch('wc/store/cart');
    if (!cart) {
      return;
    }
    if (billing && cart.setBillingAddress) {
      cart.setBillingAddress(billing);
    }
    if (shipping && cart.setShippingAddress) {
      cart.setShippingAddress(shipping);
    }
  }

  function splitName(name) {
    var parts = String(name || '').trim().split(/\s+/);
    return { first: parts.shift() || '', last: parts.join(' ') };
  }

  /* ---------------------------------------------------------------- Captcha */

  function useCaptcha() {
    var cfg = data.captcha;
    var ref = useRef(null);
    var token = useRef('');
    var widget = useRef(null);

    useEffect(function () {
      if (!cfg || cfg.provider !== 'cloudflare') {
        return undefined;
      }
      var tries = 0;
      var timer = setInterval(function () {
        tries++;
        if (window.turnstile && ref.current) {
          clearInterval(timer);
          widget.current = window.turnstile.render(ref.current, {
            sitekey: cfg.siteKey,
            // Stay hidden unless Cloudflare needs the customer to do something.
            appearance: 'interaction-only',
            callback: function (t) { token.current = t; },
            'expired-callback': function () { token.current = ''; }
          });
        } else if (tries > 40) {
          clearInterval(timer);
        }
      }, 250);
      return function () {
        clearInterval(timer);
        if (window.turnstile && widget.current !== null) {
          try { window.turnstile.remove(widget.current); } catch (e) { /* already gone */ }
        }
        widget.current = null;
      };
    }, []);

    function getToken() {
      if (!cfg) {
        return Promise.resolve('');
      }
      if (cfg.provider === 'google') {
        return new Promise(function (resolve, reject) {
          if (!window.grecaptcha) {
            reject(new Error(strings.captchaError));
            return;
          }
          window.grecaptcha.ready(function () {
            window.grecaptcha.execute(cfg.siteKey, { action: 'payment' }).then(resolve, function () {
              reject(new Error(strings.captchaError));
            });
          });
        });
      }
      return token.current ? Promise.resolve(token.current) : Promise.reject(new Error(strings.captchaMissing));
    }

    function reset() {
      // Turnstile tokens are single use.
      if (cfg && cfg.provider === 'cloudflare' && window.turnstile && widget.current !== null) {
        token.current = '';
        window.turnstile.reset(widget.current);
      }
    }

    var node = cfg && cfg.provider === 'cloudflare' ? h('div', { className: 'artspay-blocks-captcha', ref: ref }) : null;
    return { getToken: getToken, reset: reset, node: node };
  }

  /* ------------------------------------------------------ Secure hosted form */

  function useHostedCard(props) {
    var cfg = data.hostedCard;
    var frame = useRef(null);
    var sca = useRef(null);
    var submitAfterCard = useRef(false);
    var cardState = useState(null);
    var card = cardState[0];
    var setCard = cardState[1];
    var errorState = useState('');
    var error = errorState[0];
    var setError = errorState[1];
    var total = props.billing.cartTotal.value;
    var latestCard = useRef(null);
    latestCard.current = card;

    // Place order stays hidden until there's a card: Fat Zebra's Pay now button starts payment.
    useEffect(function () {
      document.body.classList.toggle('artspay-hosted-card-pending', !card);
      return function () {
        document.body.classList.remove('artspay-hosted-card-pending');
      };
    }, [card]);

    // A 3DS result is for one amount, so a new total needs a new check.
    useEffect(function () {
      sca.current = null;
    }, [total]);

    useEffect(function () {
      if (card) {
        return undefined;
      }
      var cancelled = false;
      setError('');
      fz.requestFormUrl(cfg)
        .then(function (url) {
          if (!cancelled && frame.current) {
            fz.mountFrame(frame.current, url);
          }
        })
        .catch(function (err) {
          if (!cancelled) {
            setError(err.message);
          }
        });
      return function () {
        cancelled = true;
      };
    }, [card ? card.token : '', total]);

    useEffect(function () {
      return fz.listen(cfg, {
        onHeight: function (px) {
          var iframe = frame.current && frame.current.querySelector('iframe');
          if (iframe) {
            iframe.style.height = px + 'px';
          }
        },
        onCard: function (next) {
          if (latestCard.current && latestCard.current.token === next.token) {
            return;
          }
          sca.current = null;
          submitAfterCard.current = true;
          setCard(next);
        },
        onCancel: function () {
          setError(cfg.strings.cancelled);
        }
      });
    }, []);

    // Pay now in Fat Zebra's form places the order, the same as on the classic checkout.
    useEffect(function () {
      if (card && submitAfterCard.current) {
        submitAfterCard.current = false;
        props.onSubmit();
      }
    }, [card]);

    function getData() {
      var current = latestCard.current;
      if (!current) {
        return Promise.reject(new Error(cfg.strings.enterCard));
      }
      var out = { 'artspay-card-token': current.token };
      if (!cfg.threeDs) {
        return Promise.resolve(out);
      }
      if (sca.current) {
        out['artspay-3ds'] = JSON.stringify(sca.current);
        return Promise.resolve(out);
      }
      var address = props.billing.billingAddress || {};
      return fz.verifyCard(cfg, {
        token: current.token,
        amount: props.billing.cartTotal.value,
        currency: props.billing.currency.code,
        customer: {
          firstName: address.first_name || '',
          lastName: address.last_name || '',
          email: address.email || '',
          address: address.address_1 || '',
          city: address.city || '',
          state: address.state || '',
          postcode: address.postcode || '',
          country: address.country || ''
        }
      }).then(function (result) {
        sca.current = result;
        out['artspay-3ds'] = JSON.stringify(result);
        return out;
      });
    }

    function onFail() {
      // The result was spent on the attempt that just failed.
      sca.current = null;
    }

    var node = h(
      'div',
      { className: 'artspay-hosted-card' },
      card
        ? h(
            'div',
            { className: 'artspay-hosted-card__saved' },
            h('span', { className: 'artspay-hosted-card__saved-label' }, fz.savedLabel(cfg, card)),
            ' ',
            h(
              'button',
              {
                type: 'button',
                className: 'artspay-hosted-card__change',
                onClick: function () {
                  sca.current = null;
                  setCard(null);
                }
              },
              cfg.strings.change
            )
          )
        : h(
            'div',
            { className: 'artspay-hosted-card__frame', ref: frame },
            h('p', { className: 'artspay-hosted-card__loading' }, cfg.strings.loading)
          ),
      error ? h('p', { className: 'artspay-hosted-card__error', role: 'alert' }, error) : null
    );

    return { getData: getData, onFail: onFail, node: node };
  }

  /* --------------------------------------------------------- On-site fields */

  function useOnsiteCard() {
    var number = useState('');
    var expiry = useState('');
    var cvc = useState('');
    var latest = useRef({});
    latest.current = { number: number[0], expiry: expiry[0], cvc: cvc[0] };

    function formatExpiry(value) {
      var digits = value.replace(/\D/g, '').slice(0, 4);
      return digits.length > 2 ? digits.slice(0, 2) + ' / ' + digits.slice(2) : digits;
    }

    function getData() {
      var v = latest.current;
      var digits = v.number.replace(/\D/g, '');
      var exp = v.expiry.replace(/\D/g, '');
      if (digits.length < 13 || exp.length !== 4 || v.cvc.replace(/\D/g, '').length < 3) {
        return Promise.reject(new Error(strings.cardIncomplete));
      }
      return Promise.resolve({
        'fatzebra-card-number': digits,
        'fatzebra-card-expiry': exp.slice(0, 2) + ' / ' + exp.slice(2),
        'fatzebra-card-cvc': v.cvc.replace(/\D/g, '')
      });
    }

    function input(id, label, state, attrs) {
      return h(
        'div',
        { className: 'artspay-blocks-field' },
        h('label', { htmlFor: id }, label),
        h('input', Object.assign({
          id: id,
          type: 'text',
          inputMode: 'numeric',
          value: state[0],
          onChange: function (event) { state[1](attrs.format ? attrs.format(event.target.value) : event.target.value); }
        }, attrs.input))
      );
    }

    var node = h(
      'div',
      { className: 'artspay-blocks-card' },
      input('artspay-card-number', strings.cardNumber, number, { input: { autoComplete: 'cc-number', maxLength: 23 } }),
      h(
        'div',
        { className: 'artspay-blocks-card__row' },
        input('artspay-card-expiry', strings.expiry, expiry, { format: formatExpiry, input: { autoComplete: 'cc-exp', placeholder: 'MM / YY' } }),
        input('artspay-card-cvc', strings.cvc, cvc, { input: { autoComplete: 'cc-csc', maxLength: 4 } })
      )
    );

    return { getData: getData, onFail: function () {}, node: node };
  }

  /* ------------------------------------------------------------ Card method */

  var useCard = data.cardForm === 'hosted' && data.hostedCard && fz ? useHostedCard : useOnsiteCard;

  function CardContent(props) {
    var captcha = useCaptcha();
    var card = useCard(props);
    var latest = useRef({});
    latest.current = { captcha: captcha, card: card, props: props };

    useEffect(function () {
      return props.eventRegistration.onPaymentSetup(function () {
        if (walletPending()) {
          return NOTHING;
        }
        var cur = latest.current;
        var types = cur.props.emitResponse.responseTypes;
        return Promise.all([cur.captcha.getToken(), cur.card.getData()])
          .then(function (values) {
            var paymentMethodData = Object.assign({}, values[1]);
            if (values[0]) {
              paymentMethodData.captcha_token = values[0];
            }
            var device = document.getElementById('pmnts_id');
            if (device && device.value) {
              paymentMethodData.pmnts_id = device.value;
            }
            return { type: types.SUCCESS, meta: { paymentMethodData: paymentMethodData } };
          })
          .catch(function (err) {
            return {
              type: types.ERROR,
              message: err.message,
              messageContext: cur.props.emitResponse.noticeContexts.PAYMENTS
            };
          });
      });
    }, [props.eventRegistration.onPaymentSetup]);

    useEffect(function () {
      return props.eventRegistration.onCheckoutFail(function () {
        latest.current.card.onFail();
        latest.current.captcha.reset();
        return NOTHING;
      });
    }, [props.eventRegistration.onCheckoutFail]);

    return h(
      'div',
      { className: 'artspay-blocks' },
      data.sandboxNotice ? h(el.RawHTML, null, data.sandboxNotice) : null,
      data.deviceId ? h('input', { type: 'hidden', id: 'pmnts_id', name: 'pmnts_id' }) : null,
      card.node,
      captcha.node
    );
  }

  function Label() {
    return h(
      'span',
      { className: 'artspay-blocks-label' },
      h('span', null, decode(data.title)),
      h(
        'span',
        { className: 'artspay-blocks-label__icons' },
        (data.icons || []).map(function (icon) {
          return h('img', { key: icon.id, src: icon.src, alt: icon.alt, className: 'artspay-card-logo' });
        })
      )
    );
  }

  registry.registerPaymentMethod({
    name: 'fatzebra',
    label: h(Label),
    ariaLabel: decode(data.title),
    content: h(CardContent),
    edit: h(CardContent),
    canMakePayment: function () { return true; },
    supports: { features: data.supports || ['products'] }
  });

  /* ------------------------------------------------------------- Wallets */

  // Shared by both wallets: hand the token to process_payment() once, on the wallet's own submit.
  function useWalletSetup(props, field, tokenRef) {
    useEffect(function () {
      return props.eventRegistration.onPaymentSetup(function () {
        if (!tokenRef.current) {
          return NOTHING;
        }
        var paymentMethodData = {};
        paymentMethodData[field] = tokenRef.current;
        return { type: props.emitResponse.responseTypes.SUCCESS, meta: { paymentMethodData: paymentMethodData } };
      });
    }, [props.eventRegistration.onPaymentSetup]);

    var latest = useRef(props);
    latest.current = props;

    useEffect(function () {
      function clear() {
        tokenRef.current = '';
        walletPendingSince = 0;
        return NOTHING;
      }
      // A declined wallet payment leaves WooCommerce in express mode, with the other payment
      // options hidden. onClose puts the checkout back so the customer can try again.
      function failed() {
        var ours = !!tokenRef.current;
        clear();
        if (ours) {
          latest.current.onClose();
        }
        return NOTHING;
      }
      var offFail = props.eventRegistration.onCheckoutFail(failed);
      var offSuccess = props.eventRegistration.onCheckoutSuccess(clear);
      return function () {
        offFail();
        offSuccess();
      };
    }, [props.eventRegistration.onCheckoutFail, props.eventRegistration.onCheckoutSuccess]);
  }

  if (data.googlePay) {
    var gp = data.googlePay;
    var gpClient = null;

    var getGpClient = function () {
      if (!gpClient && window.google && window.google.payments && window.google.payments.api) {
        gpClient = new window.google.payments.api.PaymentsClient({ environment: gp.environment });
      }
      return gpClient;
    };

    var gpCardMethod = function (withTokenization) {
      var method = {
        type: 'CARD',
        parameters: {
          allowedCardNetworks: gp.allowedCardNetworks,
          allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
          billingAddressRequired: true,
          billingAddressParameters: { format: 'FULL', phoneNumberRequired: true }
        }
      };
      if (withTokenization) {
        method.tokenizationSpecification = {
          type: 'PAYMENT_GATEWAY',
          parameters: { gateway: 'fatzebra', gatewayMerchantId: gp.gatewayMerchantId }
        };
      }
      return method;
    };

    var gpAddress = function (address, email) {
      if (!address) {
        return null;
      }
      var name = splitName(address.name);
      return {
        first_name: name.first,
        last_name: name.last,
        company: '',
        address_1: address.address1 || '',
        address_2: [address.address2, address.address3].filter(Boolean).join(', '),
        city: address.locality || '',
        state: address.administrativeArea || '',
        postcode: address.postalCode || '',
        country: address.countryCode || '',
        email: email || '',
        phone: address.phoneNumber || ''
      };
    };

    var GooglePayButton = function (props) {
      var mount = useRef(null);
      var token = useRef('');
      var latest = useRef(props);
      latest.current = props;
      useWalletSetup(props, 'googlepay-token', token);

      useEffect(function () {
        var client = getGpClient();
        if (!client || !mount.current) {
          return;
        }
        client
          .isReadyToPay({ apiVersion: 2, apiVersionMinor: 0, allowedPaymentMethods: [gpCardMethod(false)] })
          .then(function (res) {
            if (!res.result || !mount.current) {
              return;
            }
            mount.current.innerHTML = '';
            mount.current.appendChild(
              client.createButton({
                buttonType: gp.buttonType,
                buttonColor: gp.buttonColor,
                buttonSizeMode: 'fill',
                onClick: function () { pay(); }
              })
            );
          })
          .catch(function (err) {
            window.console.error('ArtsPay Google Pay: isReadyToPay', err);
          });
      }, []);

      function pay() {
        var p = latest.current;
        var merchantInfo = { merchantName: gp.googleMerchantName, merchantOrigin: window.location.origin };
        if (gp.googleMerchantId) {
          merchantInfo.merchantId = gp.googleMerchantId;
        }
        var request = {
          apiVersion: 2,
          apiVersionMinor: 0,
          allowedPaymentMethods: [gpCardMethod(true)],
          emailRequired: true,
          shippingAddressRequired: !!(p.shippingData && p.shippingData.needsShipping),
          transactionInfo: {
            totalPriceStatus: 'FINAL',
            totalPrice: amountString(p.billing),
            currencyCode: p.billing.currency.code
          },
          merchantInfo: merchantInfo
        };
        if (request.shippingAddressRequired) {
          request.shippingAddressParameters = gp.allowedCountryCodes.length
            ? { allowedCountryCodes: gp.allowedCountryCodes, phoneNumberRequired: true }
            : { phoneNumberRequired: true };
        }

        p.onClick();
        getGpClient()
          .loadPaymentData(request)
          .then(function (paymentData) {
            var billing = gpAddress(paymentData.paymentMethodData.info && paymentData.paymentMethodData.info.billingAddress, paymentData.email);
            var shipping = gpAddress(paymentData.shippingAddress, paymentData.email) || billing;
            setCheckoutAddresses(billing, shipping);
            // Same encoding as the classic checkout: base64 of Google's token string.
            token.current = window.btoa(paymentData.paymentMethodData.tokenizationData.token);
            walletPendingSince = Date.now();
            latest.current.onSubmit();
          })
          .catch(function (err) {
            latest.current.onClose();
            if (!err || err.statusCode !== 'CANCELED') {
              window.console.error('ArtsPay Google Pay: loadPaymentData', err);
              latest.current.setExpressPaymentError(strings.walletError);
            }
          });
      }

      return h('div', { className: 'artspay-blocks-wallet artspay-blocks-wallet--googlepay', ref: mount });
    };

    registry.registerExpressPaymentMethod({
      name: 'artspay_googlepay',
      title: 'Google Pay',
      description: 'Google Pay',
      paymentMethodId: 'fatzebra',
      content: h(GooglePayButton),
      edit: h('div', { className: 'artspay-blocks-wallet-preview' }, 'Google Pay'),
      canMakePayment: function () { return !!getGpClient(); },
      supports: { features: ['products'] }
    });
  }

  if (data.applePay) {
    var ap = data.applePay;

    var b64UrlEncode = function (str) {
      try {
        return window.btoa(unescape(encodeURIComponent(str))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
      } catch (e) {
        return '';
      }
    };

    var apAddress = function (contact) {
      if (!contact) {
        return null;
      }
      var lines = contact.addressLines || [];
      return {
        first_name: contact.givenName || '',
        last_name: contact.familyName || '',
        company: '',
        address_1: lines[0] || '',
        address_2: lines.slice(1).join(', '),
        city: contact.locality || '',
        state: contact.administrativeArea || '',
        postcode: contact.postalCode || '',
        country: String(contact.countryCode || '').toUpperCase(),
        email: contact.emailAddress || '',
        phone: contact.phoneNumber || ''
      };
    };

    var ApplePayButton = function (props) {
      var token = useRef('');
      var latest = useRef(props);
      latest.current = props;
      useWalletSetup(props, 'applepay-token', token);

      function pay() {
        var p = latest.current;
        var needsShipping = !!(p.shippingData && p.shippingData.needsShipping);
        var session = new window.ApplePaySession(3, {
          countryCode: ap.countryCode,
          currencyCode: p.billing.currency.code,
          merchantCapabilities: ['supports3DS'],
          supportedNetworks: ['visa', 'masterCard', 'amex'],
          total: { label: ap.displayName, amount: amountString(p.billing) },
          requiredBillingContactFields: ['postalAddress', 'name'],
          requiredShippingContactFields: needsShipping ? ['postalAddress', 'name', 'email', 'phone'] : ['email', 'phone']
        });

        session.onvalidatemerchant = function (event) {
          fetch(ap.restUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ validationURL: event.validationURL, displayName: ap.displayName, nonce: ap.nonce })
          })
            .then(function (res) {
              if (!res.ok) {
                throw new Error('Apple Pay session failed');
              }
              return res.json();
            })
            .then(function (merchantSession) {
              session.completeMerchantValidation(merchantSession);
            })
            .catch(function () {
              try { session.abort(); } catch (e) { /* already closed */ }
              latest.current.onClose();
              latest.current.setExpressPaymentError(strings.walletError);
            });
        };

        session.onpaymentauthorized = function (event) {
          var contact = event.payment.shippingContact || {};
          var billing = apAddress(event.payment.billingContact) || {};
          billing.email = billing.email || contact.emailAddress || '';
          billing.phone = billing.phone || contact.phoneNumber || '';
          setCheckoutAddresses(billing, needsShipping ? apAddress(contact) : billing);
          // Same encoding as the classic checkout: base64url of the token JSON.
          token.current = b64UrlEncode(JSON.stringify(event.payment.token || {}));
          session.completePayment(window.ApplePaySession.STATUS_SUCCESS);
          walletPendingSince = Date.now();
          latest.current.onSubmit();
        };

        session.oncancel = function () {
          latest.current.onClose();
        };

        // Apple Pay must start inside the click.
        p.onClick();
        session.begin();
      }

      return h(
        'div',
        { className: 'artspay-blocks-wallet artspay-blocks-wallet--applepay' },
        h('apple-pay-button', {
          buttonstyle: ap.buttonStyle,
          type: ap.buttonType,
          locale: ap.locale,
          onClick: pay
        })
      );
    };

    registry.registerExpressPaymentMethod({
      name: 'artspay_applepay',
      title: 'Apple Pay',
      description: 'Apple Pay',
      paymentMethodId: 'fatzebra',
      content: h(ApplePayButton),
      edit: h('div', { className: 'artspay-blocks-wallet-preview' }, 'Apple Pay'),
      canMakePayment: function () {
        try {
          return !!(window.ApplePaySession && window.ApplePaySession.canMakePayments());
        } catch (e) {
          return false;
        }
      },
      supports: { features: ['products'] }
    });
  }
})();
