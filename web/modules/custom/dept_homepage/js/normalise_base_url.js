/**
 * @file
 * Javascript behaviors for adjusting the toolbar's base url
 * where domain route caching might have failed.
 */
(function ($) {

  "use strict";

  Drupal.behaviors.normaliseBaseUrl = {
    attach: function (context, settings) {
      $('#toolbar-administration a[href^="http"]:not(#toolbar-item-sites-tray a)').each(function (index, linkElement) {
        let href = $(linkElement).attr('href');
        const currentDeptUrl = $(location).attr('origin');
        const currentDeptHostname = $(location).attr('host');

        // Absolute link, check if it matches our dept hostname.
        if (href.indexOf(currentDeptHostname) < 0) {
          // Not found/different, so adjust the hostname to the current dept.
          href = href.replace(href, currentDeptUrl);
          $(linkElement).attr('href', href);
        }
      });
    },
  }

}(jQuery, Drupal));
