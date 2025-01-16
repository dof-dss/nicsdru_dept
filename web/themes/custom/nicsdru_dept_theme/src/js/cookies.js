/**
 * @file
 * Provides a script to add default focus to the Cookies banner if it is open
 *
 */

/* eslint-disable */
(function ($, Drupal) {
  Drupal.behaviors.nicsdruCookieFocus = {
    attach: function attach (context) {
      window.onload = function () {
        document.querySelector('.eu-cookie-compliance-popup-open .eu-cookie-withdraw-tab').focus();
      }
    }
  }
})(jQuery, Drupal);
