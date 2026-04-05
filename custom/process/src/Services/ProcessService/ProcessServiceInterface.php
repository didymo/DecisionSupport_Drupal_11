<?php

declare(strict_types=1);

namespace Drupal\process\Services\ProcessService;

/**
 * Interface for process service.
 */
interface ProcessServiceInterface {

  /**
   * Returns a list of active Process entities.
   *
   * @return array
   *   An array of process data arrays.
   */
  public function getProcessList();

  /**
   * Get a Process by ID.
   *
   * @param int $processId
   *   The ID of the process entity to retrieve.
   *
   * @return string
   *   The JSON representation of the Process entity.
   */
  public function getProcess($processId);

  /**
   * Creates a new Process entity.
   *
   * @param array $data
   *   The data for the new entity.
   *
   * @return \Drupal\process\Entity\Process
   *   The created Process entity.
   */
  public function createProcess(array $data);

  /**
   * Duplicates a new Process entity.
   *
   * @param array $data
   *   The data for the new entity.
   *
   * @return \Drupal\process\Entity\Process
   *   The duplicated Process entity.
   */
  public function duplicateProcess(array $data);

  /**
   * Updates a Process entity.
   *
   * @param int $processId
   *   The id of the existing entity.
   * @param array $data
   *   The data of the entity.
   *
   * @return \Drupal\process\Entity\Process
   *   The updated Process entity.
   */
  public function patchProcess($processId, array $data);

  /**
   * Updates a Process Json String.
   *
   * @param int $processId
   *   The id of the existing entity.
   * @param array $data
   *   The data of the entity.
   *
   * @return \Drupal\process\Entity\Process
   *   The updated Process entity.
   */
  public function updateProcess($processId, array $data);

  /**
   * Move a existing Process entity to archived.
   *
   * @param int $processId
   *   The id of the existing entity.
   */
  public function deleteProcess($processId);

}
