<?php


use Drupal\Core\Batch\BatchBuilder;

function buildRelationshipBatch($bundle, &$etgrm_manager, &$context) {

  $count = $etgrm_manager->addToBatch($bundle);

  if (empty($context['results']['batch'])) {
    $context['results']['batch'] = $etgrm_manager->batch;
  } else {
    $context['results']['batch'] = array_merge($context['results']['batch'], $etgrm_manager->batch);
  }

  $context['results']['total'] += $count;
  $context['results']['etgrm'] = $etgrm_manager;
  // Optional message displayed under the progressbar.
  $context['message'] = t('Processing: @bundle ', ['@bundle' => $bundle,]);
}

function buildRelationshipBatchFinished($success, $results, $operations) {
  if ($success) {
    \Drupal::messenger()->addMessage(t('@total nodes processed.', ['@total' => $results['total']]));

    $batch_builder = new BatchBuilder();

    $batch_builder
      ->setTitle(t('Creating group relationships for @total nodes', ['@total' => $results['total'],]))
      ->setFile(\Drupal::service('extension.list.module')->getPath('dept_etgrm') . '/etgrm.batch.inc')
      ->setFinishCallback('processBatchIndexFinished')
      ->setErrorMessage(t('Batch has encountered an error'));

    foreach ($results['batch'] as $index => $batch) {
      $batch_builder->addOperation('processBatchIndex', [
        $index,
        $batch,
        $results['etgrm'],
      ]);
    }

    batch_set($batch_builder->toArray());
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



function processBatchIndex($index, $items, $etgrm_manager, &$context) {
  $context['results']['total'] = $etgrm_manager->process($items);
  // Optional message displayed under the progressbar.
  $context['message'] = t('Processing batch: @id ', [
    '@id' => $index,
  ]);
}

function processBatchIndexFinished($success, $results, $operations) {
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
