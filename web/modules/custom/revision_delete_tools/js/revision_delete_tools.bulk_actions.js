/**
 * @file
 * JS file for Revision Delete Tools.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.revisionDeleteTools = {
    attach: function (context, settings) {
      once('revision_delete_tools.select_all', '.select-all', context).forEach(function (select) {
        select.addEventListener('change', function () {
          document.querySelectorAll('.row-checkbox').forEach(function (checkbox) {
            if (!checkbox.attributes.disabled) {
              checkbox.checked = select.checked;
            }
          });
        });
      });
    },
  };
})(Drupal, once);
