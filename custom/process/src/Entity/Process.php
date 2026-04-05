<?php

declare(strict_types=1);

namespace Drupal\process\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\process\ProcessInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the process entity class.
 *
 * @ContentEntityType(
 *   id = "process",
 *   label = @Translation("Process"),
 *   label_collection = @Translation("Processes"),
 *   label_singular = @Translation("process"),
 *   label_plural = @Translation("processes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count processes",
 *     plural = "@count processes",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\process\ProcessListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\process\ProcessAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\process\Form\ProcessForm",
 *       "edit" = "Drupal\process\Form\ProcessForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *       "revision-delete" = \Drupal\Core\Entity\Form\RevisionDeleteForm::class,
 *       "revision-revert" = \Drupal\Core\Entity\Form\RevisionRevertForm::class,
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *       "revision" = \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider::class,
 *     },
 *   },
 *   base_table = "process",
 *   data_table = "process_field_data",
 *   revision_table = "process_revision",
 *   revision_data_table = "process_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer process",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/content/process",
 *     "add-form" = "/process/add",
 *     "canonical" = "/process/{process}",
 *     "edit-form" = "/process/{process}/edit",
 *     "delete-form" = "/process/{process}/delete",
 *     "delete-multiple-form" = "/admin/content/process/delete-multiple",
 *     "revision" = "/process/{process}/revision/{process_revision}/view",
 *     "revision-delete-form" = "/process/{process}/revision/{process_revision}/delete",
 *     "revision-revert-form" = "/process/{process}/revision/{process_revision}/revert",
 *     "version-history" = "/process/{process}/revisions",
 *   },
 *   field_ui_base_route = "entity.process.settings",
 * )
 */
final class Process extends RevisionableContentEntityBase implements ProcessInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Process entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['language'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Language'))
      ->setDescription(t('The language of the process.'))
      ->setDisplayOptions('form', [
        'type' => 'text',
        'weight' => 0,
      ])
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['revision_status'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Revision Status'))
      ->setDescription(t('The status of this revision.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'status' => 'status',
        ],
      ])
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
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

    $fields['version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Version'))
      ->setDescription(t('The version of the process.'))
      ->setDisplayOptions('form', [
        'type' => 'text',
        'weight' => 0,
      ])
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['valid'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Validity'))
      ->setDescription(t('The validity of the process.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['json_string'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('JSON String'))
      ->setDescription(t('The JSON String.'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
      ])
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
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
      ->setDescription(t('The time that the process was created.'))
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
      ->setDescription(t('The time that the process was last edited.'))
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

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel): array {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritDoc}
   */
  public function getLabel(): string {
    return $this->get('label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(string $label): ProcessInterface {
    $this->set('label', $label);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonString(): string {
    return $this->get('json_string')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setJsonString(string $jsonString): ProcessInterface {
    $this->set('json_string', $jsonString);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionStatus() {
    $targetId = $this->get('revision_status')->getValue();

    if (isset($targetId[0])) {
      $term = Term::load($targetId[0]['target_id']);

      return $term ? $term->getName() : NULL;
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setRevisionStatus($term_name) {
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $term_name, 'vid' => 'status']);
    $term = array_pop($terms);
    if ($term) {
      $this->set('revision_status', $term->id());
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    $timestamp = $this->get('created')->value;
    $date_formatter = \Drupal::service('date.formatter');
    // Format the timestamp to a  date/time.
    $formatted_date = $date_formatter->format($timestamp);
    return $formatted_date;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedTime() {
    $timestamp = $this->get('changed')->value;
    $date_formatter = \Drupal::service('date.formatter');
    // Format the timestamp to a  date/time.
    $formatted_date = $date_formatter->format($timestamp);
    return $formatted_date;
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
  public function setStatus(bool $new_status) {
    $this->set('status', $new_status);
    return $this;
  }

}
