/**
 * @file
 * Topic tree JS.
 */

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.topicTree = {
    attach: function(context, settings) {

      $(once('topic-tree', '#topic-tree-wrapper', context)).each(function () {

        const select_field = "#" + drupalSettings["topic_tree.field"];

        $(this)
          .on('changed.jstree', function (e, data) {
            // Update the hidden form field values when tree changes.
            $('input[name="selected_topics"]').val(data.instance.get_selected());
            // Also keep the label in sync with the number of selections.
            $('#topic-tree-count span').text(data.instance.get_selected().length);
          })
          .on("ready.jstree", function(e, data) {
            // Check all tree elements matching the selected options.
            $(select_field + " input:checked").each(function () {
              data.instance.select_node($(this).val());
            });
            // Disable any topic that the current node ID to prevent self referencing.
            data.instance.disable_checkbox(drupalSettings['topic_tree.current_nid']);
          })
          .on("select_node.jstree", function(e, data) {
            // Deselect all parents.
            for (const [key, value] of Object.entries(data.node.parents)) {
              data.instance.deselect_node(value);
            }

            // Deselect all children.
            for (const [key, value] of Object.entries(data.node.children_d)) {
              data.instance.deselect_node(value);
            }

            // Warn when hitting the topic selection limit.
            if (data.instance.get_selected().length > drupalSettings["topic_tree.limit"]) {
              data.instance.deselect_node(data.node);
              alert('Topic selection limit reached.')
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
            plugins: ["changed", "checkbox", "search"],
            "search": {
              "case_sensitive": false,
              "show_only_matches": true,
            }
          });

        $('#topic-tree-search').keyup(function() {
          let search_text = $(this).val();
          $('#topic-tree-wrapper').jstree('search', search_text);
        });
      });

    }
  }
})(jQuery, Drupal, drupalSettings);

/**
 * Callback for the Topic Tree form submit.
 */
(function($) {
  $.fn.topicTreeAjaxCallback = function(field, topics) {
    topics = topics.split(',');

    // Reset all checkboxes before updating with the tree values.
    $('#' + field + " input[type='checkbox']").prop("checked", false);

    topics.forEach((topic) => {
      $('#' + field + " input[value='" + topic + "']").prop("checked", true);
    });
  };
})(jQuery);
