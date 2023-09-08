/**
 * @file
 * Topic tree widget JS.
 */

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.topicTreeWidget = {
    attach: function(context, settings) {
      $(once('topic-tree-button-enable', '.topic-tree-button', context)).each(function () {
        // Removed the mock disabled state.
        $(this).removeClass('link-button-disable');
        $(this).removeAttr('title');
      });
    }
  }
})(jQuery, Drupal, drupalSettings);
