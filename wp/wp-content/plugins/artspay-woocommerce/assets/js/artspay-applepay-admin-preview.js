/**
 * Apple Pay button preview — Express checkouts (uses shared Google Pay theme + action fields).
 */
(function ($) {
  'use strict';

  var cfg = typeof artsPayApplePayPreview !== 'undefined' ? artsPayApplePayPreview : null;
  if (!cfg || !cfg.selectors || !cfg.strings || !cfg.strings.previewInline) {
    return;
  }

  var inlineMsg = cfg.strings.previewInline;

  function mapGoogleActionToApiType(stored) {
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

  function toAppleButtonType(apiType) {
    var t = String(apiType || '')
      .toLowerCase()
      .trim();
    var aliases = { checkout: 'check-out', pay: 'buy' };
    if (aliases[t]) {
      t = aliases[t];
    }
    var allowed = [
      'plain',
      'buy',
      'donate',
      'set-up',
      'continue',
      'book',
      'check-out',
      'subscribe',
      'reload',
      'add-money',
      'contribute',
      'order',
      'tip'
    ];
    if (allowed.indexOf(t) !== -1) {
      return t;
    }
    return 'buy';
  }

  function themeToAppleStyle(theme) {
    theme = String(theme || '')
      .toLowerCase()
      .trim();
    var aliases = { dark: 'black', light: 'white-outline', default: 'black', system: 'black' };
    if (aliases[theme]) {
      return aliases[theme];
    }
    var allowed = ['black', 'white', 'white-outline'];
    if (allowed.indexOf(theme) !== -1) {
      return theme;
    }
    return 'black';
  }

  function readFromSharedSettings() {
    var $a = $(cfg.selectors.buttonAction);
    var $t = $(cfg.selectors.theme);
    var rawAction = $a.length ? $a.val() : '';
    var rawTheme = $t.length ? $t.val() : '';
    return {
      buttonType: toAppleButtonType(mapGoogleActionToApiType(rawAction)),
      buttonStyle: themeToAppleStyle(rawTheme)
    };
  }

  function showPlaceholder() {
    var $mount = $('#artspay-applepay-preview-mount');
    $mount.empty().removeAttr('hidden');
    $mount.append($('<p class="artspay-gpay-preview__placeholder" />').text(inlineMsg));
  }

  function prepareMount() {
    $('#artspay-applepay-preview-mount').empty().removeAttr('hidden');
  }

  function canUseApplePayButton() {
    return typeof window.customElements !== 'undefined';
  }

  function mountAppleButton() {
    var mount = document.getElementById('artspay-applepay-preview-mount');
    if (!mount) {
      return;
    }
    while (mount.firstChild) {
      mount.removeChild(mount.firstChild);
    }

    if (!canUseApplePayButton() || !window.customElements.get('apple-pay-button')) {
      showPlaceholder();
      return;
    }

    var ts = readFromSharedSettings();
    var el = document.createElement('apple-pay-button');
    el.setAttribute('buttonstyle', ts.buttonStyle);
    el.setAttribute('type', ts.buttonType);
    if (cfg.locale) {
      el.setAttribute('locale', String(cfg.locale));
    }
    el.setAttribute('tabindex', '-1');
    el.style.pointerEvents = 'none';
    el.style.cursor = 'default';
    el.addEventListener(
      'click',
      function (e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
      },
      true
    );
    mount.appendChild(el);
  }

  function renderPreview() {
    if (cfg.requiresHttps) {
      showPlaceholder();
      return;
    }

    prepareMount();

    if (!canUseApplePayButton()) {
      showPlaceholder();
      return;
    }

    if (window.customElements.get('apple-pay-button')) {
      mountAppleButton();
      return;
    }

    window.customElements
      .whenDefined('apple-pay-button')
      .then(function () {
        mountAppleButton();
      })
      .catch(function () {
        showPlaceholder();
      });

    window.setTimeout(function () {
      var mount = document.getElementById('artspay-applepay-preview-mount');
      if (!mount || mount.querySelector('apple-pay-button') || mount.querySelector('.artspay-gpay-preview__placeholder')) {
        return;
      }
      if (!window.customElements.get('apple-pay-button')) {
        showPlaceholder();
      }
    }, 4000);
  }

  $(function () {
    renderPreview();

    $(document.body).on('change', cfg.selectors.buttonAction + ', ' + cfg.selectors.theme, function () {
      renderPreview();
    });
  });
})(jQuery);
