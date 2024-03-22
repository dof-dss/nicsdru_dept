/**
 * @file
 * Child order modal JS.
 */

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.childOrderModal = {
    attach: function (context, settings) {
      $(once('manage-topic-content-modal', '.manage-topic-content-cancel', context)).each(function () {
        $(this).click(function (event) {
          event.preventDefault();
          $("div[aria-describedby='drupal-modal'] button.ui-dialog-titlebar-close").trigger('click');
        });
      })
    }
  }
})(jQuery, Drupal, drupalSettings);


