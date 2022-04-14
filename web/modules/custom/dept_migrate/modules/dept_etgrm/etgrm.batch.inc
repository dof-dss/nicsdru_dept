<?php

function processGroupNodes($id, $nid, $group_ids, $etgrm_manager, &$context) {

  $etgrm_manager->single($nid, $group_ids);

  $context['results'][] = $id;
  // Optional message displayed under the progressbar.
  $context['message'] = t('Processing: @id ', [
    '@id' => $id,
  ]);
}

function processGroupNodesFinished($success, $results, $operations) {
  if ($success) {
    // Here we could do something meaningful with the results.
    // We just display the number of nodes we processed...
    \Drupal::messenger()->addMessage(t('@count results processed.', [
      '@count' => count($results),
    ]));
  }
  else {
    // An error occurred.
    // $operations contains the operations that remained unprocessed.
    $error_operation = reset($operations);
    \Drupal::messenger()->addError(t('An error occurred while processing @operation with arguments : @args', [
      '@operation' => $error_operation[0],
      '@args' => print_r($error_operation[0], TRUE),
    ]));
  }

}
