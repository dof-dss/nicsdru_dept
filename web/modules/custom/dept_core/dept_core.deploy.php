<?php

/**
 * @file
 * Drush deploy hook callbacks.
 */

use Drupal\field\Entity\FieldConfig;

/**
 * Populate department correspondence emails after config import.
 */
function dept_core_deploy_set_department_correspondence_emails(array &$sandbox) {
  $field = FieldConfig::loadByName(
    'department',
    'department',
    'field_dept_correspondence_email'
  );

  if (!$field) {
    \Drupal::logger('dept_core')->warning(
      'Skipping correspondence email backfill because field config is missing.'
    );
    return;
  }

  $emails = [
    'nigov' => 'info@executiveoffice-ni.gov.uk',
    'executiveoffice' => 'info@executiveoffice-executive.gov.uk',
    'daera' => 'daerawebsite-admin@daera-ni.gov.uk',
    'communities' => 'CorporateCommunications@communities-ni.gov.uk',
    'education' => 'Press.Office@education-ni.gov.uk',
    'economy' => 'DFEMail@economy-ni.gov.uk',
    'finance' => 'dof.enquiries@finance-ni.gov.uk',
    'infrastructure' => 'info@infrastructure-ni.gov.uk',
    'health' => 'webmaster@health-ni.gov.uk',
    'justice' => 'DoJ.info@justice-ni.gov.uk',
  ];

  $departments = \Drupal::service('department.manager')->getAllDepartments();

  foreach ($departments as $department) {
    $id = $department->id();
    if (isset($emails[$id])) {
      $department->set('field_dept_correspondence_email', $emails[$id]);
      $department->save();
    }
  }
}
