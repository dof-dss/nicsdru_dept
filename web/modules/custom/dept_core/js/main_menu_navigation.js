/**
 * @file
 * Javascript behavior for fixing the home link on the main navigation menu.
 *
 * We have an unusual issue whereby the Home link is sometimes rendered as an
 * anchor when viewing the homepage instead if the span element.
 * We have tried a variety of server side fixes for this, some of which work but
 * not always for anonymous visitors.
 *
 */
(function ($) {
  "use strict";

  Drupal.behaviors.mainMenuNavigation = {
    attach: function (context, settings) {
      let homeLink = $('#nav-main-menu .nav-menu li:first').children().first();

      if (homeLink.prop('nodeName') === 'A') {
        homeLink.replaceWith('<span class="active link__self">Home</span>')
      }
    }
  }

}(jQuery, Drupal));
