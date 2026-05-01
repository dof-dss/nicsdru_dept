<?php

namespace Drupal\dept_fs\Plugin\MediaDuplicatesChecksum;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media_duplicates\Plugin\MediaDuplicatesChecksumBase;

/**
 * Golang file checksum processor.
 *
 * @MediaDuplicatesChecksum(
 *   id = "gofileprocessor",
 *   label = @Translation("Go File Processor"),
 *   media_types = {"file", "image", "audio_file", "video_file"},
 * )
 */
class GoFileProcessor extends MediaDuplicatesChecksumBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function getChecksum(Media $media) {
    $source = $media->getSource();

    /** @var \Drupal\file\Entity\File $file */
    $file = $media->get($source->configuration['source_field'])->entity;
    return ($file instanceof FileInterface) ? $this->goHash($file->getFileUri()) : NULL;
  }

  /**
   * Create a hash for a given file URI.
   *
   * @param string $fileUri
   *   The URI of the file to hash.
   *
   * @return string|null
   *   The hash of the file or NULL.
   */
  protected function goHash($fileUri) {
    $absolute_path = \Drupal::service('file_system')->realpath($fileUri);
    $output = NULL;
    $result = NULL;

    // Path to the Go based filehasher (See: /scripts/go/file_hash).
    $exe_path = (getenv('IS_DDEV_PROJECT')) ? '/var/www/html/bin/filehash' : '/app/bin/filehash';

    exec($exe_path . ' ' . escapeshellarg($absolute_path), $output, $result);

    if ($result == 0 && !empty($output)) {
      return $output[0];
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getChecksumData(Media $media) {
    // This is not used for this implementation.
    return NULL;
  }

}
