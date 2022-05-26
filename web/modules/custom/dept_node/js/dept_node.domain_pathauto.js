/**
 * @file
 * Drupal behaviors for dept_node domain pathauto.
 */

(function ($, Drupal) {
  Drupal.behaviors.deptNodeDomainPathauto = {
    attach: function (context, settings) {
      // Event handler for publish to department checkboxes.
      $( '#edit-groups input[type="checkbox"]', context ).change(function(e) {
        if ($(this).prop('checked')) {
          $('#edit-path-0-domain-path-group-' + $(this).val() + '-pathauto').prop('checked', true);
        } else {
          $('#edit-path-0-domain-path-group-' + $(this).val() + '-pathauto').prop('checked', false);
        }
      });
    }
  };
})(jQuery, Drupal);
