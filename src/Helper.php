<?php

namespace Drupal\animation;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

class Helper {
  use StringTranslationTrait;

  protected EntityTypeManagerInterface $entityManager;

  protected EntityStorageInterface $storage;

  protected Renderer $renderer;

  /**
   * Constructs a new FormHelper object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Renderer $renderer
  ) {
    $this->entityManager = $entity_type_manager;
    $this->storage = $this->entityManager->getStorage('animation');
    $this->renderer = $renderer;
  }

  public function getCode(NodeInterface $site): string {
    $nodes = [$site->id() => $site];
    $paragraphs = [];
    foreach($nodes as $page) {
      if ($page->hasField('field_ff_content_story_blocks')) {
        $paragraphs = array_merge($paragraphs, $page->get('field_ff_content_story_blocks')
          ->referencedEntities());
      }
      if ($page->hasField('field_catalog_items')) {
        $paragraphs = array_merge($paragraphs, $page->get('field_catalog_items')->referencedEntities());
      }
    }

    $animation_code = '';

    /**
     * @var Paragraph $paragraph
     */
    foreach($paragraphs as $paragraph) {
      // Get config code.
      if ($paragraph->hasField('field_animation_config')) {
        if (!empty($animations = $paragraph->get('field_animation_config')->referencedEntities())) {
          $animation = $animations[0];
          $animation_code .= $animation->get('gsap_code');
          // Replace options.
          if ($paragraph->hasField('field_animation_code')) {
            $option_string = str_replace('options=', '', $paragraph->get('field_animation_code')->value);
            $options = explode('|', $option_string);
            foreach($options as $option) {
              if (!empty($option_parts = explode('=', $option))) {
                if (count($option_parts) != 2) {
                  continue;
                }
                $animation_code = str_replace("[$option_parts[0]]", $option_parts[1], $animation_code);
              }
            }
          }

          // Replace section ids.
          $section_id = $this->getSectionId($paragraph);
          $animation_code = str_replace('[section_id]', "#$section_id", $animation_code);
          // Replace any [timeline] tokens with a random valid js variable name.
          $var_name = $this->getRandomVarName();
          $animation_code = str_replace('[timeline]', $var_name, $animation_code);

          // Add refresh on widget loaded events.
          $animation_code .= "\ndocument.addEventListener('section-loaded', function() {\n" . "  if (typeof $var_name !== 'undefined') {\n  if (typeof $var_name.scrollTrigger !== 'undefined') {\n$var_name.scrollTrigger.refresh()\n  }\n}" . "});\n";
          // Disable loading=lazy for any images.
          // Since some animated paragrahs (eg header) dont have a valid $section_id we skip those.
          if ($section_id != $paragraph->id() && !is_numeric($section_id)) {
            $animation_code .= "gsap.utils.toArray('#$section_id img').forEach(function(image) { \nimage.removeAttribute('loading');\n});\n";
          }
        }
      }
    }
    return "<script>$animation_code</script>";
  }

  /**
   * @param $paragraph
   *
   * @return array
   */
  private function getExistingAnimationOptions(Paragraph $paragraph): array {
    $existing = $paragraph->get('field_animation_code')->value;
    preg_match('/options=([#+-\-_%a-zA-Z0-9.=|]+)/', $existing, $matches);
    $default_options = [];
    if (!empty($matches[1])) {
      $parts = explode('|', $matches[1]);
      foreach($parts as $option) {
        $key_val = explode('=', $option);
        if (count($key_val) < 2) {
          continue;
        }
        $default_options[$key_val[0]] = $key_val[1];
      }
      return $default_options;
    }
    return [];
  }


  /**
   * Gets the currently selected animation for the paragraph.
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *
   * @return false|mixed
   */
  public function getExistingAnimationSelected(Paragraph $paragraph) {
    if (!empty($existing = $paragraph->get('field_animation_config')->referencedEntities())) {
      return $existing[0]->id();
    }
    return null;
  }

  /**
   * Gets the options for an animation form, existing or default.
   * @param $animation_id
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *
   * @return array
   * @throws \Exception
   */
  public function getAnimationOptions($animation_id, Paragraph $paragraph): array {
    // Build the custom options.
    $list = [];
    if (!empty($animation = $this->storage->load($animation_id))) {
      $options = $animation->get('custom_fields');
      $existing = $this->getExistingAnimationOptions($paragraph);
      $existing_animation = $this->getExistingAnimationSelected($paragraph);

      // Build the demo section.
      $list['details'] = [
        '#type' => 'container',
        '#title' => $this->t('Details'),
        '#attributes' => [
          'id' => 'animation-details'
        ]
      ];

      // NOTE: this is a custom theme for the animation, defined in this animation.theme.
      $list['details']['content'] = [
        '#theme' => 'animation_info',
        '#animation' => $animation
      ];

      // Build the custom options fields.
      $list['options'] = [
        '#type' => 'details',
        '#title' => $this->t('Customize Animation'),
        '#tree' => TRUE,
        '#states' => [
          'visible' => [
            'select[name="animation"]' => array('!value' => '')
          ]
        ]
      ];
      foreach($options as $option) {
        $list['options'][$option['id']] = [
          '#type' => $option['type'],
          '#title' =>  $this->t($option['label']),
          '#description' => $this->t($option['description']),
          '#description_display' => 'after',
        ];
        if (($existing_animation == $animation_id) && (isset($existing[$option['id']]))) {
          $list['options'][$option['id']]['#default_value'] = $existing[$option['id']];
        }
        else {
          $list['options'][$option['id']]['#value'] = $option['default_value'] ?? null;
        }
      }
    }
    return $list;
  }

  /**
   * Generates a random var for each timeline in animation code.
   * @return string
   */
  public function getRandomVarName(): string {
    $characters = '_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < 7; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  /**
   * Returns a sane label for a section.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return mixed
   */
  private function getSectionLabel(ParagraphInterface $paragraph) {
    $result = $paragraph->id();
    if ($paragraph->hasField('field_title')) {
      $result = $paragraph->get('field_title')->value;
    }
    return strip_tags((string)$result);
  }

  private function getSectionId(Paragraph $paragraph) {
    // This is the default.
    $id = Html::getId("paragraph-id-{$paragraph->id()}");
    return $id;
  }
}
