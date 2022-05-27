/**
 * @file
 * Drupal behaviors for dept_node domain pathauto.
 */

(function ($, Drupal) {
  Drupal.behaviors.deptNodeDomainPathauto = {
    attach: function (context, settings) {

      // Update Domain Pathauto generate URL option depending on Publish to options.
      function updateDomainPathauto(element) {
        if ($(element).prop('checked')) {
          $('#edit-path-0-domain-path-group-' + $(element).val() + '-pathauto').prop('checked', true);
        } else {
          $('#edit-path-0-domain-path-group-' + $(element).val() + '-pathauto').prop('checked', false);
        }
      }

      // Update Pathauto on page load.
      $('#edit-groups input[type="checkbox"]', context).each(function () {
        updateDomainPathauto($(this));
      });

      // Event handler for publish to department checkboxes.
      $('#edit-groups input[type="checkbox"]', context).change(function() {
        updateDomainPathauto($(this));
      });
    },
  };
})(jQuery, Drupal);
