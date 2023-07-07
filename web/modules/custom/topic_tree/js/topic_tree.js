/**
 * @file
 * Topic tree JS.
 */

// Codes run both on normal page loads and when data is loaded by AJAX (or BigPipe!)
// @See https://www.drupal.org/docs/8/api/javascript-api/javascript-api-overview
(function($, Drupal, once) {
  Drupal.behaviors.topicTree = {
    attach: function(context, settings) {
      $('#topic-tree-wrapper').jstree({
        core: {
          data: {
            url: function(node) {
              return Drupal.url(
                "admin/topics/json"
              );
            },
            data: function(node) {
              return {
                id: node.id,
                text: node.text,
                parent: node.parent
              };
            }
          },
        },
        checkbox: {
          three_state: false
        },
        plugins: ["changed", "checkbox", "conditionalselect"]
      });
    }
  }
})(jQuery, Drupal, once);

