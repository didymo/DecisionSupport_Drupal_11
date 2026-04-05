<?php

declare(strict_types=1);

namespace Drupal\decision_support\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\decision_support\DecisionSupportInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the decision support entity class.
 *
 * @ContentEntityType(
 *   id = "decision_support_entity",
 *   label = @Translation("Decision Support"),
 *   label_collection = @Translation("Decision Supports"),
 *   label_singular = @Translation("decision support"),
 *   label_plural = @Translation("decision supports"),
 *   label_count = @PluralTranslation(
 *     singular = "@count decision supports",
 *     plural = "@count decision supports",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\decision_support\DecisionSupportListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\decision_support\DecisionSupportAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\decision_support\Form\DecisionSupportForm",
 *       "edit" = "Drupal\decision_support\Form\DecisionSupportForm",
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
 *   base_table = "decision_support_entity",
 *   data_table = "decision_support_entity_field_data",
 *   revision_table = "decision_support_entity_revision",
 *   revision_data_table = "decision_support_entity_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer decision_support_entity",
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
 *     "collection" = "/admin/content/entity",
 *     "add-form" = "/support/add",
 *     "canonical" = "/support/{decision_support_entity}",
 *     "edit-form" = "/support/{decision_support_entity}/edit",
 *     "delete-form" = "/support/{decision_support_entity}/delete",
 *     "delete-multiple-form" = "/admin/content/entity/delete-multiple",
 *     "revision" = "/support/{decision_support_entity}/revision/{decision_support_entity_revision}/view",
 *     "revision-delete-form" = "/support/{decision_support_entity}/revision/{decision_support_entity_revision}/delete",
 *     "revision-revert-form" = "/support/{decision_support_entity}/revision/{decision_support_entity_revision}/revert",
 *     "version-history" = "/support/{decision_support_entity}/revisions",
 *   },
 *   field_ui_base_route = "entity.decision_support_entity.settings",
 * )
 */
final class DecisionSupport extends RevisionableContentEntityBase implements DecisionSupportInterface {

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
      ->setDescription(t('The name of the Decision Support entity.'))
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
      ->setDescription(t('The language of the decision support.'))
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

    $fields['completed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Completed'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Version'))
      ->setDescription(t('The version of the decision support.'))
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
      ->setDescription(t('The validity of the decision support.'))
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
      ->setDescription(t('The time that the decision support was created.'))
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
      ->setDescription(t('The time that the decision support was last edited.'))
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
  public function getName(): string {
    return $this->get('label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): DecisionSupportInterface {
    $this->set('label', $name);
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
  public function setJsonString(string $jsonString): DecisionSupportInterface {
    $this->set('json_string', $jsonString);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUid() {
    return $this->getOwnerId();
  }

  /**
   * Sets the owner user ID.
   */
  public function setUid(int $uid): DecisionSupportInterface {
    $this->setOwnerId($uid);
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
   * Gets the updated time.
   */
  public function getUpdatedTime() {
    $timestamp = $this->get('changed')->value;
    $date_formatter = \Drupal::service('date.formatter');
    // Format the timestamp to a  date/time.
    $formatted_date = $date_formatter->format($timestamp);
    return $formatted_date;
  }

  /**
   * Gets the related process label from the stored JSON payload.
   */
  public function getProcessLabel(): string {
    $json = $this->get('json_string')->value ?? '';
    $jsonData = json_decode($json, TRUE);
    return is_array($jsonData) && isset($jsonData['processLabel']) ? (string) $jsonData['processLabel'] : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIsCompleted(): bool {
    return (bool) $this->get('completed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIsCompleted(bool $completed): self {
    $this->set('completed', $completed);
    return $this;
  }

}
