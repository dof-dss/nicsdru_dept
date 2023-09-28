/**
 * @file
 * Child order modal JS.
 */

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.childOrderModal = {
    attach: function (context, settings) {
      $(once('child-order-modal', '.child-order-cancel', context)).each(function () {
        $(this).click(function (event) {
          event.preventDefault();
          $("div[aria-describedby='drupal-modal'] button.ui-dialog-titlebar-close").trigger('click');
        });
      })
    }
  }
})(jQuery, Drupal, drupalSettings);


