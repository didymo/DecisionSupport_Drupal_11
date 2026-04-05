<?php

declare(strict_types=1);

namespace Drupal\decision_support\Services\DecisionSupport;

/**
 * Interface for decision support service.
 */
interface DecisionSupportServiceInterface {

  /**
   * Returns a list of in-progress DecisionSupport entities.
   *
   * @return array
   *   An array of decision support data arrays.
   */
  public function getDecisionSupportList();

  /**
   * Returns a list of completed DecisionSupport entities.
   *
   * @return array
   *   An array of decision support report data arrays.
   */
  public function getDecisionSupportReportList();

  /**
   * Get a DecisionSupport JSON string by ID.
   *
   * @param int $decisionSupportId
   *   The ID of the decision support entity to retrieve.
   *
   * @return string
   *   The JSON string of the DecisionSupport entity.
   */
  public function getDecisionSupport($decisionSupportId);

  /**
   * Get a formatted DecisionSupport report by ID.
   *
   * @param int $decisionSupportId
   *   The ID of the decision support entity to retrieve.
   *
   * @return string
   *   The JSON-encoded report data.
   */
  public function getDecisionSupportReport($decisionSupportId);

  /**
   * Creates a new DecisionSupport entity.
   *
   * @param array $data
   *   The data for the new entity.
   *
   * @return \Drupal\decision_support\Entity\DecisionSupport
   *   The created DecisionSupport entity.
   */
  public function createDecisionSupport(array $data);

  /**
   * Updates a DecisionSupport entity.
   *
   * @param int $decisionSupportId
   *   The id of the existing entity.
   * @param array $data
   *   The data of the entity.
   *
   * @return \Drupal\decision_support\Entity\DecisionSupport
   *   The updated DecisionSupport entity.
   */
  public function updateDecisionSupport($decisionSupportId, array $data);

  /**
   * Move an existing DecisionSupport entity to archived.
   *
   * Renames the label, unpublishes the entity, and sets revision status to
   * Archived. The entity is preserved in the database and remains visible to
   * administrators via the Drupal admin UI.
   *
   * @param int $decisionSupportId
   *   The id of the existing entity.
   *
   * @return \Drupal\decision_support\DecisionSupportInterface
   *   The archived entity.
   */
  public function archiveDecisionSupport($decisionSupportId);

}
