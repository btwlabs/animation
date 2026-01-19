<?php

namespace Drupal\animation\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\animation\AnimationInterface;
use Drupal\Core\Entity\Annotation\ConfigEntityType;

/**
 * Defines the animation entity type.
 *
 * @ConfigEntityType(
 *   id = "animation",
 *   label = @Translation("Animation"),
 *   label_collection = @Translation("Animation"),
 *   label_singular = @Translation("Animation"),
 *   label_plural = @Translation("Animation"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Animation",
 *     plural = "@count Animations",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\animation\animationListBuilder",
 *     "form" = {
 *       "add" = "Drupal\animation\Form\animationForm",
 *       "edit" = "Drupal\animation\Form\animationForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "animation",
 *   admin_permission = "administer animation",
 *   links = {
 *     "collection" = "/admin/structure/animation",
 *     "add-form" = "/admin/structure/animation/add",
 *     "edit-form" = "/admin/structure/animation/{animation}",
 *     "delete-form" = "/admin/structure/animation/{animation}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "allowed_story_blocks",
 *     "custom_fields",
 *     "icon",
 *     "gsap_code"
 *   }
 * )
 */
class Animation extends ConfigEntityBase implements AnimationInterface {

  /**
   * The animations ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The animations label.
   *
   * @var string
   */
  protected $label;

  /**
   * The animations status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The animations description.
   *
   * @var string
   */
  protected $description;

  protected $allowed_story_blocks;

  protected $custom_fields;

  protected $icon;

  protected $gsap_code;

}
