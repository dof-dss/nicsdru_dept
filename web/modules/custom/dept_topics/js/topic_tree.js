/**
 * @file
 * Topic tree JS.
 */

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.topicTree = {
    attach: function(context, settings) {
      let select_field = "#" + drupalSettings["topic_tree.field"];
      $('#topic-tree-wrapper')
        .on('changed.jstree', function (e, data) {
          // Unselect all selected options on the field select.
          $(select_field + " option:selected").prop("selected", false)
          // Select the options on the field select element to match the tree.
          for(i = 0; i < data.selected.length; i++) {
            $(select_field + " option[value='" + data.instance.get_node(data.selected[i]).id + "']").prop("selected", true);
          }
        })
        .on("ready.jstree", function(e, data) {
          data.instance.select_node($(select_field).val());
        })
        .jstree({
          core: {
            data: {
              url: function(node) {
                return Drupal.url(
                  "admin/topics/topic_tree/json/" + drupalSettings["topic_tree.department"]
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
})(jQuery, Drupal, drupalSettings);

