<?php

/**
 * @file
 * Contains \Drupal\media_image_popup\Controller\MediaImagePopup.
 */

namespace Drupal\media_image_popup\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Url;

/**
 * Class MediaImagePopup.
 *
 * @package Drupal\media_image_popup\Controller
 */
class MediaImagePopup extends ControllerBase {
  /**
   * Render.
   *
   * @return string
   *   Return Hello string.
   */
  public function render($fid, $image_style = NULL) {
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);

    if (!empty($image_style)) {
      $image_style = ImageStyle::load($image_style);
    }
    $image_uri = $file->getFileUri();

    if (!empty($image_style)) {
      $absolute_path = ImageStyle::load($image_style->getName())->buildUrl($image_uri);
    }
    else {
      // Get absolute path for original image.
      $absolute_path = Url::fromUri(file_create_url($image_uri))->getUri();
    }
    return [
      '#theme' => 'media_image_popup_details',
      '#url_popup' => $absolute_path,
    ];
  }

}
