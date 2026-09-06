/**
 * ArtsPay frontend bootstrap — shared namespace and utilities.
 * Merges PHP-localized `artsPayLocalized` into window.ArtsPay.
 */
(function ($) {
  'use strict';

  window.ArtsPay = window.ArtsPay || {};

  if (typeof artsPayLocalized !== 'undefined' && artsPayLocalized) {
    var loc = artsPayLocalized;
    if (loc.version) {
      window.ArtsPay.version = loc.version;
    }
    if (loc.config) {
      window.ArtsPay.config = $.extend({}, window.ArtsPay.config || {}, loc.config);
    }
    if (loc.googlePay) {
      window.ArtsPay.googlePay = loc.googlePay;
      window.fzgooglepay = loc.googlePay;
    }
    if (loc.captcha) {
      window.ArtsPay.captcha = loc.captcha;
      window.artspay_captcha = loc.captcha;
    }
  }

  if (typeof artsPayApplePayLocalized !== 'undefined' && artsPayApplePayLocalized) {
    var apLoc = artsPayApplePayLocalized;
    if (apLoc.applePay) {
      window.ArtsPay.applePay = apLoc.applePay;
    }
  }

  window.ArtsPay.utils = window.ArtsPay.utils || {};

  window.ArtsPay.utils.debounce = function (fn, wait) {
    var t;
    return function () {
      var ctx = this;
      var args = arguments;
      clearTimeout(t);
      t = setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };
  };

  window.ArtsPay.utils.getGooglePayConfig = function () {
    if (window.ArtsPay.googlePay) {
      return window.ArtsPay.googlePay;
    }
    if (typeof fzgooglepay !== 'undefined') {
      return fzgooglepay;
    }
    return null;
  };

  window.ArtsPay.utils.log = function () {
    if (typeof window.console !== 'undefined' && window.console.log) {
      window.console.log.apply(window.console, arguments);
    }
  };
})(jQuery);
