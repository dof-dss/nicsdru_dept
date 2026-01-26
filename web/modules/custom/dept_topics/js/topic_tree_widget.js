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

  // Click handler to open the topic tree modal from the Topics field widget.
  Drupal.behaviors.topicTreeOpenModalButton = {
    attach: function(context, settings) {
      $(once('topic-tree-open-modal-button', '#site-topics-tree-open-button', context)).each(function () {
        $(this).on('click', function(e) {
          const ajaxSettings = {
            url: $(this).attr('data-topic-modal-url'),
            dialogType: 'modal',
            dialog: {
              title: $(this).attr('data-topic-modal-title'),
              width: 800,
              minHeight: 500,
              position: {
                my: 'center top',
                at: 'center top'
              },
              draggable: true,
              autoResize: false,
              dialogClass: 'topic-widget-modal',
            },
          };
          const topicModal = Drupal.ajax(ajaxSettings);
          topicModal.execute();
        });
        // Adding Playwright test id after event handler has been attached to button.
        $(this).attr( "data-testid", 'site-topics-tree-open-button' );
      });
    }
  }

})(jQuery, Drupal, drupalSettings);
