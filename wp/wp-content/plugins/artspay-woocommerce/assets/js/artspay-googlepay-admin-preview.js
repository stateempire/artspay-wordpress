/**
 * Google Pay button preview on WooCommerce → ArtsPay → Express checkouts (non-interactive).
 */
(function ($) {
  'use strict';

  var cfg = typeof artsPayGpayPreview !== 'undefined' ? artsPayGpayPreview : null;
  if (!cfg || !cfg.selectors || !cfg.strings || !cfg.strings.previewInline) {
    return;
  }

  var inlineMsg = cfg.strings.previewInline;
  var paymentsClient = null;
  var gpayReady = false;

  function mapButtonType(stored) {
    stored = String(stored || '')
      .toLowerCase()
      .trim();
    var legacy = { icon_only: 'plain', buy_with: 'buy' };
    if (legacy[stored]) {
      return legacy[stored];
    }
    var allowed = ['book', 'buy', 'checkout', 'donate', 'order', 'pay', 'plain', 'subscribe'];
    if (allowed.indexOf(stored) !== -1) {
      return stored;
    }
    return 'buy';
  }

  function themeToColor(theme) {
    if (theme === 'dark') {
      return 'black';
    }
    if (theme === 'light') {
      return 'white';
    }
    return 'default';
  }

  function merchantFromForm() {
    var $m = $(cfg.selectors.merchantId);
    if ($m.length) {
      return String($m.val() || '').trim();
    }
    return String(cfg.gatewayMerchantId || '').trim();
  }

  function getFormButtonTypeAndColor() {
    return {
      buttonType: mapButtonType($(cfg.selectors.buttonAction).val()),
      buttonColor: themeToColor($(cfg.selectors.theme).val())
    };
  }

  /** Unified copy in the button slot (merchant, HTTPS, browser, Google Pay, Apple Pay note). */
  function showMountPlaceholder() {
    var $mount = $('#artspay-gpay-preview-mount');
    $mount.empty().removeAttr('hidden');
    $mount.append($('<p class="artspay-gpay-preview__placeholder" />').text(inlineMsg));
  }

  function prepareMountForButton() {
    $('#artspay-gpay-preview-mount').empty().removeAttr('hidden');
  }

  function baseCardPaymentMethod() {
    return {
      type: 'CARD',
      parameters: {
        allowedCardNetworks: ['VISA', 'MASTERCARD', 'AMEX', 'JCB'],
        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS']
      }
    };
  }

  function ensureClient() {
    if (typeof google === 'undefined' || !google.payments || !google.payments.api) {
      return null;
    }
    if (!paymentsClient) {
      paymentsClient = new google.payments.api.PaymentsClient({
        environment: cfg.environment
      });
    }
    return paymentsClient;
  }

  function mountButton(bc) {
    var mount = document.getElementById('artspay-gpay-preview-mount');
    if (!mount) {
      return;
    }
    while (mount.firstChild) {
      mount.removeChild(mount.firstChild);
    }
    var gc = ensureClient();
    if (!gc) {
      showMountPlaceholder();
      return;
    }
    var btn = gc.createButton({
      buttonType: bc.buttonType,
      buttonColor: bc.buttonColor,
      buttonSizeMode: 'fill',
      onClick: function () {
        return false;
      }
    });
    btn.setAttribute('tabindex', '-1');
    btn.style.pointerEvents = 'none';
    btn.style.cursor = 'default';
    mount.appendChild(btn);
  }

  function renderPreview() {
    if (cfg.requiresHttps) {
      gpayReady = false;
      showMountPlaceholder();
      return;
    }

    var merchant = merchantFromForm();
    if (!merchant) {
      gpayReady = false;
      showMountPlaceholder();
      return;
    }

    var gc = ensureClient();
    if (!gc) {
      showMountPlaceholder();
      return;
    }

    var bc = getFormButtonTypeAndColor();
    var base = {
      apiVersion: 2,
      apiVersionMinor: 0,
      allowedPaymentMethods: [baseCardPaymentMethod()]
    };

    if (gpayReady) {
      prepareMountForButton();
      mountButton(bc);
      return;
    }

    gc.isReadyToPay(base)
      .then(function (res) {
        if (!res || !res.result) {
          showMountPlaceholder();
          return;
        }
        gpayReady = true;
        prepareMountForButton();
        mountButton(bc);
      })
      .catch(function () {
        showMountPlaceholder();
      });
  }

  $(function () {
    renderPreview();

    $(document.body).on('change', cfg.selectors.buttonAction + ', ' + cfg.selectors.theme, function () {
      renderPreview();
    });

    $(document.body).on('change', cfg.selectors.merchantId, function () {
      gpayReady = false;
      renderPreview();
    });
  });
})(jQuery);
