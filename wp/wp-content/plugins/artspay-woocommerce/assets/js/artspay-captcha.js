/* global grecaptcha */
/* global turnstile */
/* global jQuery */

/**
 * ArtsPay Captcha Handler — WooCommerce checkout (reCAPTCHA v3 + Turnstile).
 * Config: window.ArtsPay.captcha (preferred) or legacy artspay_captcha.
 */
function artspayGetCaptchaConfig() {
  if (window.ArtsPay && window.ArtsPay.captcha) {
    return window.ArtsPay.captcha;
  }
  if (typeof artspay_captcha !== 'undefined') {
    return artspay_captcha;
  }
  return null;
}

class ArtsPayCaptcha {
  constructor() {
    var cfg = artspayGetCaptchaConfig();
    if (!cfg) {
      return;
    }
    this.provider = cfg.provider;
    this.siteKey = cfg.site_key;

    this.widgetState = {
      isRendered: false,
      isRendering: false,
      widgetId: null,
      retryCount: 0,
      maxRetries: 3
    };

    this.debounceTimer = null;
    this.debounceDelay = 300;

    this.tokenGeneratedAt = null;

    this.init();
  }

  init() {
    var container = document.getElementById('captcha-container');
    if (!container) {
      return;
    }

    this.setupAjaxInterception();

    if (this.provider === 'google') {
      container.style.display = 'none';
      this.setupGoogleReCaptcha();
    } else if (this.provider === 'cloudflare') {
      this.setupCloudflareTurnstile();
    }

    this.setupWooCommerceEvents();
  }

  setupAjaxInterception() {
    var self = this;

    jQuery(document).ajaxSend(function (event, jqXHR, ajaxOptions) {
      if (ajaxOptions.url && ajaxOptions.url.indexOf('wc-ajax=checkout') !== -1) {
        if (self.isArtsPayGatewaySelected()) {
          var tokenInput = jQuery('#captcha_token');
          if (tokenInput.val()) {
            if (ajaxOptions.data && ajaxOptions.data.indexOf('captcha_token=') === -1) {
              ajaxOptions.data += '&captcha_token=' + encodeURIComponent(tokenInput.val());
            }
          }
        }
      }
    });
  }

  setupWooCommerceEvents() {
    var self = this;

    jQuery(document.body).on('checkout_error', function () {
      self.handleCheckoutUpdate();
    });

    jQuery(document.body).on('update_checkout', function () {
      if (self.tokenGeneratedAt) {
        var now = Date.now();
        var tokenAge = (now - self.tokenGeneratedAt) / 1000;

        if (tokenAge >= 90) {
          self.handleCheckoutUpdate();
        }
      }
    });
  }

  handleCheckoutUpdate() {
    if (this.isArtsPayGatewaySelected()) {
      if (this.provider === 'google') {
        this.generateReCaptchaToken();
      } else if (this.provider === 'cloudflare') {
        this.resetWidgetState();
        this.renderTurnstileWidget();
      }
    }
  }

  renderTurnstileWidget() {
    var container = document.getElementById('captcha-container');
    if (!container) {
      return;
    }

    if (this.widgetState.isRendering || this.widgetState.isRendered) {
      return;
    }

    this.widgetState.isRendering = true;

    this.cleanupTurnstileWidget();

    try {
      this.widgetState.widgetId = turnstile.render(container, {
        sitekey: this.siteKey,
        callback: (token) => this.setToken(token),
        'expired-callback': () => this.handleTurnstileExpired(),
        'error-callback': (error) => this.handleTurnstileError(error)
      });

      this.widgetState.isRendered = true;
      this.widgetState.isRendering = false;
      this.widgetState.retryCount = 0;
    } catch (error) {
      this.handleTurnstileError(error);
    }
  }

  handleTurnstileExpired() {
    this.setToken('');
    this.resetTurnstileWidget();
  }

  handleTurnstileError(error) {
    console.error('Turnstile error:', error);
    this.widgetState.isRendering = false;
    this.setToken('');

    if (this.widgetState.retryCount < this.widgetState.maxRetries) {
      this.widgetState.retryCount++;
      var retryDelay = Math.pow(2, this.widgetState.retryCount) * 1000;

      setTimeout(() => {
        if (this.isArtsPayGatewaySelected()) {
          this.renderTurnstileWidget();
        }
      }, retryDelay);
    } else {
      console.error('Max Turnstile retries exceeded');
      this.resetWidgetState();
    }
  }

  setupGoogleReCaptcha() {
    if (this.isArtsPayGatewaySelected()) {
      this.generateReCaptchaToken();
    }
  }

  setupCloudflareTurnstile() {
    if (this.isArtsPayGatewaySelected()) {
      this.renderTurnstileWidget();
    }
  }

  resetTurnstileWidget() {
    if (typeof turnstile !== 'undefined' && this.widgetState.widgetId !== null) {
      try {
        turnstile.reset(this.widgetState.widgetId);
        this.setToken('');
      } catch (error) {
        console.error('Turnstile reset error:', error);
        this.cleanupTurnstileWidget();
      }
    }
  }

  cleanupTurnstileWidget() {
    var container = document.getElementById('captcha-container');
    if (container) {
      container.innerHTML = '';
    }
    this.resetWidgetState();
  }

  resetWidgetState() {
    this.widgetState.isRendered = false;
    this.widgetState.isRendering = false;
    this.widgetState.widgetId = null;
    this.widgetState.retryCount = 0;
  }

  async generateReCaptchaToken() {
    try {
      if (typeof grecaptcha === 'undefined') {
        return;
      }

      await new Promise((resolve) => grecaptcha.ready(resolve));
      var token = await grecaptcha.execute(this.siteKey, { action: 'payment' });
      this.setToken(token);

      this.tokenGeneratedAt = Date.now();
    } catch (err) {
      console.error('reCAPTCHA failed:', err);
    }
  }

  isArtsPayGatewaySelected() {
    var creditCardRadio = document.querySelector('input[name="payment_method"][value="fatzebra"]');
    return creditCardRadio && creditCardRadio.checked;
  }

  setToken(token) {
    var tokenInput = document.getElementById('captcha_token');
    if (tokenInput) {
      tokenInput.value = token;
    }
  }

  clearToken() {
    this.setToken('');
  }
}

document.addEventListener('DOMContentLoaded', function () {
  var cfg = artspayGetCaptchaConfig();
  if (cfg && document.getElementById('captcha-container')) {
    window.artspayCapcha = new ArtsPayCaptcha();
  }
});
