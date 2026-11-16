/**
 * ArtsPay hosted card form and 3D Secure helpers, shared by the classic and block checkouts.
 *
 * The card form is Fat Zebra's hosted payments page in tokenize-only mode. 3D Secure runs with
 * fatzebra.js against the card token it returns. Config: window.ArtsPay.hostedCard (classic) or
 * the block payment method data, passed in as `cfg`.
 */
(function () {
  'use strict';

  window.ArtsPay = window.ArtsPay || {};

  var sdk = null;
  var sdkLoading = null;
  var pending = null;

  function post(url, data) {
    var body = new URLSearchParams();
    Object.keys(data).forEach(function (key) {
      body.append(key, data[key] == null ? '' : String(data[key]));
    });
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(function (res) {
      return res.json().catch(function () {
        return null;
      }).then(function (json) {
        return { ok: res.ok, json: json };
      });
    });
  }

  /** Signed hosted form URL for the current cart, or a pay-for-order order. */
  function requestFormUrl(cfg, orderId, orderKey) {
    return post(cfg.formUrl, { nonce: cfg.nonce, order_id: orderId || 0, order_key: orderKey || '' }).then(function (res) {
      if (!res.ok || !res.json || !res.json.success || !res.json.data || !res.json.data.url) {
        throw new Error(cfg.strings.formError);
      }
      return res.json.data.url;
    });
  }

  /** Read a postMessage from the hosted form, which may be an object, JSON or a query string. */
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

  /**
   * Listen to the hosted form. Calls onCard({ token, number, type }) when a card is entered,
   * onHeight(px) to resize, and onCancel(). Returns a function that stops listening.
   */
  function listen(cfg, handlers) {
    function onMessage(event) {
      if (event.origin !== cfg.paynowOrigin) {
        return;
      }
      var msg = parseMessage(event.data);
      if (!msg) {
        return;
      }
      var height = msg.height || msg.height_px || (msg.data && msg.data.height);
      if (height && parseInt(height, 10) > 0 && handlers.onHeight) {
        handlers.onHeight(parseInt(height, 10));
      }
      var data = msg.data && typeof msg.data === 'object' ? msg.data : msg;
      if (msg.message === 'transaction.complete' && handlers.onCard) {
        var token = data.token || data.t || msg.token || msg.t || '';
        if (token) {
          handlers.onCard({ token: token, number: data.card_number || '', type: data.card_type || '' });
        }
      } else if (msg.message === 'transaction.cancelled' && handlers.onCancel) {
        handlers.onCancel();
      }
    }
    window.addEventListener('message', onMessage);
    return function () {
      window.removeEventListener('message', onMessage);
    };
  }

  /** Replace a container's contents with the hosted form iframe. */
  function mountFrame(container, url) {
    var iframe = document.createElement('iframe');
    iframe.src = url;
    iframe.title = 'Secure card form';
    iframe.className = 'artspay-hosted-card__iframe';
    iframe.setAttribute('scrolling', 'no');
    iframe.setAttribute('allowtransparency', 'true');
    container.innerHTML = '';
    container.appendChild(iframe);
    return iframe;
  }

  /** Label for a saved card, e.g. "VISA ending 1000". */
  function savedLabel(cfg, card) {
    var digits = String((card && card.number) || '').replace(/\D/g, '').slice(-4);
    return digits
      ? cfg.strings.saved.replace('%1$s', (card && card.type) || 'Card').replace('%2$s', digits)
      : cfg.strings.savedFallback;
  }

  function settle(method, value) {
    var current = pending;
    pending = null;
    if (current) {
      current[method](value);
    }
  }

  function loadSdk(cfg) {
    if (sdk) {
      return Promise.resolve(sdk);
    }
    if (sdkLoading) {
      return sdkLoading;
    }
    sdkLoading = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = cfg.sdkUrl;
      script.async = true;
      script.onload = function () {
        if (typeof window.FatZebra !== 'function') {
          sdkLoading = null;
          reject(new Error(cfg.strings.verifyError));
          return;
        }
        sdk = new window.FatZebra({ username: cfg.username });
        sdk.on('fz.sca.success', function (event) {
          settle('resolve', (event.detail && event.detail.data) || {});
        });
        sdk.on('fz.sca.error', function () {
          settle('reject', new Error(cfg.strings.verifyFailed));
        });
        resolve(sdk);
      };
      script.onerror = function () {
        sdkLoading = null;
        reject(new Error(cfg.strings.verifyError));
      };
      document.head.appendChild(script);
    });
    return sdkLoading;
  }

  /**
   * Run 3D Secure on a card token. Resolves with the fz.sca.success data, or rejects with an
   * Error whose message can be shown to the customer.
   *
   * @param {Object} cfg  Hosted card config.
   * @param {Object} opts { token, amount (cents), currency, customer }.
   */
  function verifyCard(cfg, opts) {
    if (pending) {
      return Promise.reject(new Error(cfg.strings.verifyError));
    }
    return post(cfg.prepareUrl, { nonce: cfg.nonce, card_token: opts.token })
      .then(function (res) {
        if (!res.ok || !res.json || !res.json.success) {
          // The store sends its own message when the 3D Secure check limit is reached.
          var data = res.json && res.json.data;
          throw new Error((data && data.message) || cfg.strings.verifyError);
        }
        try {
          // fatzebra.js reads its access token from here.
          window.localStorage.setItem('fz-access-token', res.json.data.accessToken);
        } catch (e) {
          throw new Error(cfg.strings.verifyError);
        }
        return loadSdk(cfg).then(function (instance) {
          // Fat Zebra can take a moment to make a new card token usable (as in artspay-pay).
          return new Promise(function (resolve) {
            setTimeout(function () {
              resolve({ instance: instance, verification: res.json.data.verification });
            }, 500);
          });
        });
      })
      .then(function (ctx) {
        return new Promise(function (resolve, reject) {
          pending = { resolve: resolve, reject: reject };
          var result = ctx.instance.verifyCard({
            customer: opts.customer,
            paymentIntent: {
              payment: {
                amount: opts.amount,
                currency: opts.currency,
                reference: 'wc-' + Date.now()
              },
              verification: ctx.verification
            },
            paymentMethod: { type: 'card_on_file', data: { token: opts.token } }
          });
          // Some failures only reject the promise and never fire fz.sca.error.
          if (result && typeof result.catch === 'function') {
            result.catch(function (err) {
              window.console.error('ArtsPay: verifyCard failed', err && (err.response ? JSON.stringify(err.response.data) : err.message || err));
              settle('reject', new Error(cfg.strings.verifyError));
            });
          }
        });
      });
  }

  window.ArtsPay.fatZebra = {
    requestFormUrl: requestFormUrl,
    parseMessage: parseMessage,
    listen: listen,
    mountFrame: mountFrame,
    savedLabel: savedLabel,
    verifyCard: verifyCard
  };
})();
