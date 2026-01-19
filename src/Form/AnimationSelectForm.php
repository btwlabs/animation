<?php

namespace Drupal\animation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\paragraphs\Entity\Paragraph;
use http\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AnimationSelectForm.
 */
class AnimationSelectForm extends FormBase {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $animationStorage;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityManager;

  /**
   * @var \Drupal\animation\Helper
   */
  protected $helper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->helper = $container->get('animation.helper');
    $instance->entityManager = $container->get('entity_type.manager');
    $instance->animationStorage = $instance->entityManager->getStorage('animation');
    $instance->messenger = \Drupal::messenger();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'animation_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $root_parent_type = null, $root_parent= null, Paragraph $paragraph = null) {
    /**
     * @var \Drupal\paragraphs\Entity\Paragraph $paragraph
     */
    if (!($paragraph instanceof Paragraph)) {
      throw new InvalidArgumentException($this->t('Cannot load a paragraph from id: :id', [':id' => $paragraph->id()]));
    }

    $form_state->set('paragraph', $paragraph);

    // Get available animations.
    $query = $this->animationStorage->getQuery()
      ->accessCheck(false)
      ->condition('status', 1)
      ->condition('allowed_story_blocks.*.bundle', [$paragraph->bundle()], 'IN');
    if (empty($res = $query->execute())) {
      return [
        'message' => [
          '#type' => 'markup',
          '#markup' => '<div class="no-animations">There are currently no animations available for this story section type.</div>'
        ]
      ];
    }

    $options = [];
    foreach($res as $item) {
      $animation = $this->animationStorage->load($item);
      $options[$animation->id()] = $animation->label();
    }

    if (isset($form_state->getUserInput()['animation'])) {
      $new_value = $form_state->getUserInput()['animation'];
    }
    else {
      $new_value = $this->helper->getExistingAnimationSelected($paragraph);
    }

    $form['animation'] = [
      '#type' => 'select',
      '#title' => $this->t('Animation'),
      '#description' => $this->t('Select an animation to use on this story section.'),
      '#options' => $options,
      '#weight' => '0',
      '#required' => false,
      '#multiple' => false,
      '#empty_option' => $this->t('-Select an Animation-'),
      '#ajax' => [
        'callback' => '::ajaxReturn',
        'disable-focus' => TRUE,
        'event' => 'change',
        'wrapper' => 'custom-options'
      ],
      '#default_value' => $new_value
    ];

    // Generate custom fields.
    $form['options'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'custom-options'
      ],
      '#tree' => TRUE
    ];
    if ($new_value) {
      $form['options'] += $this->helper->getAnimationOptions($new_value, $form_state->get('paragraph'));
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function ajaxReturn(array $form, FormStateInterface $form_state) {
    return $form['options'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Initialize the options values.
    $values = $input = (!empty($form_state->getValue('options'))) ? $form_state->getValue('options')['options'] : [];
    if (isset($form_state->getUserInput()['options'])) {
      $input = array_merge($input, $form_state->getUserInput()['options']['options']);
    }
    if ($input != $values) {
      $values = $input;
    }

    /**
     * @var \Drupal\paragraphs\Entity\Paragraph $paragraph
     */
    $paragraph = $form_state->get('paragraph');
    $selectedValue = $form_state->getValue('animation');

    // Build the custom options.
    $code = '';
    if (!empty($animation = $this->animationStorage->load($selectedValue))) {
      // Add animation info.
      $code .= "options=";
      foreach($values as $option_name => $option_value) {
        // Get code from animation.
        $code .= "$option_name=$option_value|";
      }
    }
    // Save the animation code onto the paragraph.
    if ($paragraph->hasField('field_animation_code') && $paragraph->hasField('field_animation_config')) {
      $paragraph->set('field_animation_code', $code)->set('field_animation_config', $animation)->save();
      $this->messenger->addStatus($this->t('Animation was saved successfully.'));
    }
    else {
      $this->messenger->addWarning($this->t('There was a problem saving your animation. Please contact an administrator. No animation field was present.'));
    }

    $parent = $paragraph->getParentEntity();
    $form_state->setRedirect('entity.node.canonical', ['node' => $parent->id()]);
  }

}
