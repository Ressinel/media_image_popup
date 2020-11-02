<?php

namespace Drupal\media_image_popup\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'media_image_popup' formatter.
 *
 * @FieldFormatter(
 *   id = "media_image_popup",
 *   label = @Translation("Media Image Popup"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MediaImagePopupFormatter extends ImageFormatter {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an MediaImagePopupFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\image\ImageStyleStorageInterface $image_style_storage
   *   The image style entity storage handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, ImageStyleStorageInterface $image_style_storage, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   *
   * This has to be overridden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image_style' => '',
      'image_style_popup' => '',
      'image_link' => '',
     ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $image_styles = image_style_options(FALSE);

    $elements = parent::settingsForm($form, $form_state);
    unset($elements['image_link']);

    $elements['image_style'] = [
      '#title' => t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
    ];

    $elements['image_style_popup'] = [
      '#title' => t('Popup Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style_popup'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('image_style');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = t('Image style: @style', ['@style' => $image_styles[$image_style_setting]]);
    }
    else {
      $summary[] = t('Image style: @style', ['@style' => t('Original image')]);
    }
    $image_style_popup_setting = $this->getSetting('image_style_popup');
    if (isset($image_styles[$image_style_popup_setting])) {
      $summary[] = t('Image style popup: @style', ['@style' => $image_styles[$image_style_popup_setting]]);
    }
    else {
      $summary[] = t('Image style popup: @style', ['@style' => t('Original image')]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    $image_style_popup = $this->getSetting('image_style_popup');
    $image_style_name = $this->getSetting('image_style');
    $image_fid = $media_items[0]->get('field_media_image')->target_id;

    $image_style_setting = $this->getSetting('image_style');

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {

      $image_output = [];
      if ($this->getSetting('image_style')) {
        $image_output = [
          '#theme' => 'image_style',
          '#style_name' => $this->getSetting('image_style'),
        ];
      }
      else {
        $image_output = [
          '#theme' => 'image',
        ];
      }
    
      $item = $media->get('thumbnail')->first();
    
      // Do not output an empty 'title' attribute.
      if (mb_strlen($item->title) != 0) {
        $image_output['#title'] = $item->title;
      }
    
      if (($entity = $item->entity) && empty($item->uri)) {
        $image_output['#uri'] = $entity->getFileUri();
      }
      else {
        $image_output['#uri'] = $item->uri;
      }
    
      foreach (['width', 'height', 'alt'] as $key) {
        $image_output["#$key"] = $item->$key;
      }

      global $base_url;
      $popup_width = 750;
      $img = render($image_output);
      $img_link = "<a href='" . $base_url . "/media_image_popup/render/" . $image_fid. "/" .  $image_style_popup. "' class='use-ajax' data-dialog-type='modal'>" . $img . "</a>";
      $elements[$delta] = [
        '#markup' => $img_link,
        '#attached' => [
          'library'=> [
            'core/drupal.dialog.ajax'
          ]
        ],
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for entity types that reference
    // media items.
    return ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media');
  }

}
