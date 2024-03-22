/**
 * @file
 * Child order modal JS.
 */

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.childOrderModal = {
    attach: function (context, settings) {

      // I feel duuurty doing this.
      $(once('manage-topic-content-row-weights-button', '.tabledrag-toggle-weight', context)).each(function () {
        $(this).addClass('button');
      })

      $(once('manage-topic-content-cancel', '.manage-topic-content-cancel', context)).each(function () {
        $(this).click(function (event) {
          event.preventDefault();
          $("div[aria-describedby='drupal-modal'] button.ui-dialog-titlebar-close").trigger('click');
        });
      })
    }
  }
})(jQuery, Drupal, drupalSettings);


