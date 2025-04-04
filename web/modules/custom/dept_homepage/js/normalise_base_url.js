/**
 * @file
 * Javascript behaviors for adjusting the toolbar's base url
 * where domain route caching might have failed.
 */
(function ($) {

  "use strict";

  Drupal.behaviors.normaliseBaseUrl = {
    attach: function (context, settings) {
      $('#nav-main-menu a, #toolbar-administration a[href^="http"]:not(#toolbar-item-sites-tray a)').each(function (index, linkElement) {
        let href = $(linkElement).attr('href');
        const currentDeptHostname = $(location).attr('host');

        // Absolute link, check if it matches our dept hostname.
        if (href.indexOf(currentDeptHostname) < 0) {
          // Not found/different, so adjust the hostname to the current dept.
          // Use this createelement technique to support easier extraction
          // of a hostname from string variable.
          let tmpLink = document.createElement('a');
          tmpLink.href = href;
          const hrefHostname = tmpLink.hostname;

          // Swap it into place. It has to replace the hostname only to preserve
          // the existing protocol and path of the URL.
          href = href.replace(hrefHostname, currentDeptHostname);
          $(linkElement).attr('href', href);
        }
      });
    },
  }

}(jQuery, Drupal));
