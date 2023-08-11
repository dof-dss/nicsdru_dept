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
          $(select_field + " input:checked").prop("checked", false)
          // Select the options on the field select element to match the tree.
          for(i = 0; i < data.selected.length; i++) {
            $(select_field + " input[value='" + data.instance.get_node(data.selected[i]).id + "']").prop("checked", true);
          }
        })
        .on("ready.jstree", function(e, data) {
          // Check all tree elements matching the selected options.
          $(select_field + " input:checked").each(function () {
            data.instance.select_node($(this).val());
          });
        })
        .on("select_node.jstree", function(e, data) {
          for (const [key, value] of Object.entries(data.node.parents)) {
            data.instance.deselect_node(value);
          }

          for (const [key, value] of Object.entries(data.node.children)) {
            data.instance.deselect_node(value);
          }
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
          plugins: ["changed", "checkbox", "conditionalselect", "search"],
          "search": {
            "case_sensitive": false,
            "show_only_matches": true,
          }
        });

      $('#topic-tree-search').keyup(function() {
        let search_text = $(this).val();
        $('#topic-tree-wrapper').jstree('search', search_text);
      });
    }
  }
})(jQuery, Drupal, drupalSettings);

