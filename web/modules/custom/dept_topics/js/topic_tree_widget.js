/**
 * @file
 * Topic tree widget JS.
 */

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.topicTreeWidget = {
    attach: function(context, settings) {
      $(once('topic-tree-button-enable', '.topic-tree-button', context)).each(function () {
        $(this).removeClass('link-button-disable');
      });
    }
  }
})(jQuery, Drupal, drupalSettings);
