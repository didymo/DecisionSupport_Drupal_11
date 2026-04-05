<?php

declare(strict_types=1);

namespace Drupal\decision_support_file\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\decision_support_file\DecisionSupportFileInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the decision support file entity class.
 *
 * @ContentEntityType(
 *   id = "decision_support_file",
 *   label = @Translation("Decision Support File"),
 *   label_collection = @Translation("Decision Support Files"),
 *   label_singular = @Translation("decision support file"),
 *   label_plural = @Translation("decision support files"),
 *   label_count = @PluralTranslation(
 *     singular = "@count decision support files",
 *     plural = "@count decision support files",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\decision_support_file\DecisionSupportFileListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\decision_support_file\DecisionSupportFileAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\decision_support_file\Form\DecisionSupportFileForm",
 *       "edit" = "Drupal\decision_support_file\Form\DecisionSupportFileForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "decision_support_file",
 *   data_table = "decision_support_file_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer decision_support_file",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/decision-support-file",
 *     "add-form" = "/decision-support-file/add",
 *     "canonical" = "/decision-support-file/{decision_support_file}",
 *     "edit-form" = "/decision-support-file/{decision_support_file}/edit",
 *     "delete-form" = "/decision-support-file/{decision_support_file}/delete",
 *     "delete-multiple-form" = "/admin/content/decision-support-file/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.decision_support_file.settings",
 * )
 */
final class DecisionSupportFile extends ContentEntityBase implements DecisionSupportFileInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('text_long')
      ->setTranslatable(TRUE)
      ->setLabel(t('Notes'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the decision support file was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the decision support file was last edited.'));

    $fields['decisionSupportId'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Decision Support ID'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['visible'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Visible'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['stepId'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Step ID'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('File'))
      ->setDescription(t('Upload a file'))
      ->setSettings([
        'file_directory' => 'private://decision_support_files',
        'file_extensions' => 'doc xls pdf ppt pps odt ods odp txt mp3 mov mpg flv m4v mp4 ogg ovg wmv png gif jpg jpeg ico',
        'max_filesize' => '',
        'handler' => 'default:file',
      ])
      ->setDisplayOptions('form', [
        'type' => 'file',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->get('label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(string $label): self {
    $this->set('label', $label);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(bool $status): self {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotes(): string {
    return $this->get('notes')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setNotes(string $notes): self {
    $this->set('notes', $notes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): string {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime(): string {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDecisionSupportId(): int {
    return (int) $this->get('decisionSupportId')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDecisionSupportId(int $decisionSupportId): self {
    $this->set('decisionSupportId', $decisionSupportId);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisible(): bool {
    return (bool) $this->get('visible')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setVisible(bool $visible): self {
    $this->set('visible', $visible);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStepId(): string {
    return $this->get('stepId')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepId(string $stepId): self {
    $this->set('stepId', $stepId);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileId(): ?int {
    $file = $this->get('file')->entity;
    return $file ? (int) $file->id() : NULL;
  }

}
