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
   * Get a hash of the file.
   *
   * This is a duplicate of Crypt::hashBase64() but using the file handling.
   *
   * @param string $filename
   *   The file to hash.
   *
   * @return string
   *   The hash of the file.
   */
  protected function goHash($fileUri) {
    $absolute_path = \Drupal::service('file_system')->realpath($fileUri);

    $output=null;
    $result=null;

    if (getenv('IS_DDEV_PROJECT')) {
      $exe_path = '/var/www/html/bin/filehash';
    } else {
      $exe_path = '/app/bin/filehash';
    }


    exec($exe_path . ' "' . $absolute_path . '"' , $output, $result);

    if ($result == 0 && !empty($output)) {
      return str_replace(['+', '/', '='], ['-', '_', ''], $output[0]);
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
