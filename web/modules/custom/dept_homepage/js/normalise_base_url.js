/**
 * @file
 * Javascript behaviors for adjusting the toolbar's base url
 * where domain route caching might have failed.
 */
(function ($) {

  "use strict";

  Drupal.behaviors.normaliseBaseUrl = {
    attach: function (context, settings) {
      $('a[href^="http"]:not(#toolbar-item-sites-tray a)').each(function (index, linkElement) {
        let href = $(linkElement).attr('href');
        const currentDeptHostname = $(location).attr('host');

        // Absolute link, check if it matches our dept hostname.
        if (href.indexOf(currentDeptHostname) < 0) {
          // Not found/different, so adjust the hostname to the current dept.
          href = href.replace(href, currentDeptHostname);
          $(linkElement).attr('href', href);
        }
      });
    },
  }

}(jQuery, Drupal));
