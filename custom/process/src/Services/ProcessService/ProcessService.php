<?php

declare(strict_types=1);

namespace Drupal\process\Services\ProcessService;

use Drupal\process\Entity\Process;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service Class for handling Process.
 */
final class ProcessService implements ProcessServiceInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a ProcessService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessList() {

    $ids = $this->entityTypeManager
      ->getStorage('process')
      ->getQuery()
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->execute();

    $processList = [];
    foreach ($this->entityTypeManager->getStorage('process')->loadMultiple($ids) as $entity) {
      if ($entity instanceof Process) {
        $process['label'] = $entity->getLabel();
        $process['entityId'] = $entity->id();
        $process['revisionId'] = $entity->getRevisionId();
        $process['revisionCreationTime'] = $entity->getRevisionCreationTime();
        $process['createdTime'] = $entity->getCreatedTime();
        $process['updatedTime'] = $entity->getUpdatedTime();
        $process['revisionStatus'] = $entity->getRevisionStatus();
        $process['enabled'] = $entity->getStatus();
        $process['json_string'] = $entity->getJsonString();

        $processList[] = $process;
      }
    }

    return $processList;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcess($processId) {

    /** @var \Drupal\process\Entity\Process|null $process */
    $process = $this->entityTypeManager->getStorage('process')->load($processId);

    if (!$process) {
      throw new NotFoundHttpException(sprintf('Process with ID %s was not found.', $processId));
    }
    if (!$process->getStatus()) {
      throw new NotFoundHttpException(sprintf('Process with ID %s was not found.', $processId));
    }

    return $process->getJsonString();
  }

  /**
   * {@inheritdoc}
   */
  public function createProcess(array $data) {
    if (empty($data['revision_status'])) {
      throw new BadRequestHttpException('Missing required field: revision_status');
    }

    $process = Process::create($data);
    $process->setRevisionStatus($data['revision_status']);
    $process->save();

    $jsonstring = [
      'entityId' => $process->id(),
      'uuid' => $process->uuid(),
      'label' => $process->label(),
      'steps' => [],
    ];
    $process->setNewRevision(FALSE);
    $process->setJsonString(json_encode($jsonstring));
    $process->save();

    // Log the creation of the entity.
    $this->logger->notice('Created new Process entity with ID @id.', ['@id' => $process->id()]);
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function duplicateProcess(array $data) {
    if (empty($data['revision_status']) || empty($data['json_string'])) {
      throw new BadRequestHttpException('Missing required fields for process duplication');
    }

    $data_jsonstring = json_decode($data['json_string'], TRUE);
    if (!is_array($data_jsonstring) || !isset($data_jsonstring['steps']) || !is_array($data_jsonstring['steps'])) {
      throw new BadRequestHttpException('Invalid process json_string payload');
    }

    $process = Process::create($data);
    $process->setRevisionStatus($data['revision_status']);
    $process->save();

    $newjsonstring = [
      'entityId' => $process->id(),
      'uuid' => $process->uuid(),
      'label' => $process->label(),
      'steps' => $data_jsonstring['steps'],
    ];
    $process->setNewRevision(FALSE);
    $process->setJsonString(json_encode($newjsonstring));
    $process->save();

    // Log the creation of the entity.
    $this->logger->notice('Duplicated Process entity with new ID @id.', ['@id' => $process->id()]);
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function patchProcess($processId, array $data) {
    /** @var \Drupal\process\Entity\Process|null $process */
    $process = $this->entityTypeManager->getStorage('process')->load($processId);

    if (!$process) {
      throw new NotFoundHttpException(sprintf('Process with ID %s was not found.', $processId));
    }

    if (empty($data['label']) || empty($data['revision_status'])) {
      throw new BadRequestHttpException('Missing required fields for process update');
    }

    $process->setLabel($data['label']);
    $process->setRevisionStatus($data['revision_status']);
    $process->save();

    $this->logger->notice('The Process @id has been updated.', ['@id' => $processId]);

    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function updateProcess($processId, array $data) {
    /** @var \Drupal\process\Entity\Process|null $process */
    $process = $this->entityTypeManager->getStorage('process')->load($processId);

    if (!$process) {
      throw new NotFoundHttpException(sprintf('Process with ID %s was not found.', $processId));
    }
    if ($data === []) {
      throw new BadRequestHttpException('Missing process update payload');
    }
    $json_string = json_encode($data);

    $process->setJsonString($json_string);
    $process->save();

    $this->logger->notice('The Process @id has been updated.', ['@id' => $processId]);

    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteProcess($processId) {

    /** @var \Drupal\process\Entity\Process|null $process */
    $process = $this->entityTypeManager->getStorage('process')->load($processId);
    if (!$process) {
      throw new NotFoundHttpException(sprintf('Process with ID %s was not found.', $processId));
    }
    $label = $process->getLabel();
    $newLabel = "$label - Archived";
    $process->setLabel($newLabel);
    $process->setStatus(FALSE);
    $process->setRevisionStatus("Archived");
    $process->save();

    $this->logger->notice('Moved Process with ID @id to archived.', ['@id' => $processId]);

    return $process;
  }

}
