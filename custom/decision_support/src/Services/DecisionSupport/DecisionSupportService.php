<?php

declare(strict_types=1);

namespace Drupal\decision_support\Services\DecisionSupport;

use Drupal\decision_support\Entity\DecisionSupport;
use Drupal\decision_support_file\Services\DecisionSupportFile\DecisionSupportFileServiceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service class for handling decision support entities.
 */
final class DecisionSupportService implements DecisionSupportServiceInterface {

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
   * The decision support file service.
   *
   * @var \Drupal\decision_support_file\Services\DecisionSupportFile\DecisionSupportFileServiceInterface
   */
  protected DecisionSupportFileServiceInterface $decisionSupportFileService;

  /**
   * Constructs a DecisionSupportService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, DecisionSupportFileServiceInterface $decision_support_file_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->decisionSupportFileService = $decision_support_file_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getDecisionSupportList() {

    $ids = $this->entityTypeManager
      ->getStorage('decision_support_entity')
      ->getQuery()
      ->condition('completed', 0)
      ->accessCheck(TRUE)
      ->execute();

    $decisionSupportList = [];
    foreach ($this->entityTypeManager->getStorage('decision_support_entity')->loadMultiple($ids) as $entity) {
      if ($entity instanceof DecisionSupport) {
        $decisionSupport['label'] = $entity->getName();
        $decisionSupport['entityId'] = $entity->id();
        $decisionSupport['revisionId'] = $entity->getRevisionId();
        $decisionSupport['createdTime'] = $entity->getCreatedTime();
        $decisionSupport['updatedTime'] = $entity->getUpdatedTime();
        $decisionSupport['revisionStatus'] = $entity->getRevisionStatus();
        $decisionSupport['processLabel'] = $entity->getProcessLabel();
        $decisionSupport['isCompleted'] = $entity->getIsCompleted();
        $decisionSupport['json_string'] = $entity->getJsonString();

        $decisionSupportList[] = $decisionSupport;
      }
    }

    return $decisionSupportList;
  }

  /**
   * {@inheritdoc}
   */
  public function getDecisionSupportReportList() {

    $ids = $this->entityTypeManager
      ->getStorage('decision_support_entity')
      ->getQuery()
      ->condition('completed', 1)
      ->accessCheck(TRUE)
      ->execute();

    $decisionSupportReportList = [];
    foreach ($this->entityTypeManager->getStorage('decision_support_entity')->loadMultiple($ids) as $entity) {
      if ($entity instanceof DecisionSupport) {
        $decisionSupportReport['label'] = $entity->getName();
        $decisionSupportReport['entityId'] = $entity->id();
        $decisionSupportReport['submittedTime'] = $entity->getUpdatedTime();
        $decisionSupportReport['processLabel'] = $entity->getProcessLabel();

        $decisionSupportReportList[] = $decisionSupportReport;
      }
    }

    return $decisionSupportReportList;
  }

  /**
   * {@inheritdoc}
   */
  public function getDecisionSupportReport($decisionSupportId) {

    /** @var \Drupal\decision_support\Entity\DecisionSupport|null $decisionSupport */
    $decisionSupport = $this->entityTypeManager->getStorage('decision_support_entity')->load($decisionSupportId);
    if (!$decisionSupport) {
      throw new NotFoundHttpException(sprintf('DecisionSupport with ID %s was not found.', $decisionSupportId));
    }
    $decisionSupportJsonString = $decisionSupport->getJsonString();

    $jsonData = json_decode($decisionSupportJsonString, TRUE);
    if (!is_array($jsonData) || !isset($jsonData['steps']) || !is_array($jsonData['steps'])) {
      throw new BadRequestHttpException('Decision support report data is malformed');
    }

    // Fetch all files for this decision support once, outside the loop.
    $files = $this->decisionSupportFileService->getDecisionSupportFile($decisionSupportId);

    $reportData = [];
    $stepsData = [];
    foreach ($jsonData['steps'] as $step) {
      $stepData['step'] = [
        'id' => $step['id'] ?? NULL,
        'description' => $step['description'] ?? '',
        'answerLabel' => $step['answerLabel'] ?? '',
        'textAnswer' => strip_tags($step['textAnswer'] ?? ''),
      ];

      // Filter the files to match the current step.
      $stepFiles = array_filter($files, function ($file) use ($step) {
        return isset($step['id']) && $file['stepId'] == $step['id'];
      });

      // Add the attached files to the step data.
      $stepData['attachedFiles'] = array_values(array_map(function ($file) {
        return [
          'label' => $file['label'],
          'entityId' => $file['entityId'],
          'fileEntityId' => $file['fileEntityId'],
          'isVisible' => $file['isVisible'],
        ];
      }, $stepFiles));

      $stepsData[] = $stepData;
    }
    $reportData['steps'] = $stepsData;
    $reportData['reportLabel'] = $decisionSupport->getName();
    $reportData['processLabel'] = $decisionSupport->getProcessLabel();
    $reportData['submittedTime'] = $decisionSupport->getUpdatedTime();

    $reportJson = json_encode($reportData);

    return $reportJson;

  }

  /**
   * {@inheritdoc}
   */
  public function getDecisionSupport($decisionSupportId) {

    /** @var \Drupal\decision_support\Entity\DecisionSupport|null $decisionSupport */
    $decisionSupport = $this->entityTypeManager->getStorage('decision_support_entity')->load($decisionSupportId);
    if (!$decisionSupport) {
      throw new NotFoundHttpException(sprintf('DecisionSupport with ID %s was not found.', $decisionSupportId));
    }
    $decisionSupportJsonString = $decisionSupport->getJsonString();

    return $decisionSupportJsonString;
  }

  /**
   * {@inheritdoc}
   */
  public function createDecisionSupport(array $data) {
    if (empty($data['process_id'])) {
      throw new BadRequestHttpException('Missing required field: process_id');
    }

    $processId = $data['process_id'];
    /** @var \Drupal\process\Entity\Process|null $process */
    $process = $this->entityTypeManager->getStorage('process')->load($processId);
    if (!$process) {
      throw new NotFoundHttpException(sprintf('Process with ID %s was not found.', $processId));
    }
    $processJson = $process->getJsonString();
    $processData = json_decode($processJson, TRUE);
    if (!is_array($processData) || !isset($processData['steps']) || !is_array($processData['steps'])) {
      throw new BadRequestHttpException('Process data is malformed');
    }

    $decisionSupport = DecisionSupport::create($data);
    $decisionSupport->setIsCompleted(FALSE);
    $decisionSupport->save();

    $jsonstring = [
      'entityId' => $decisionSupport->id(),
      'uuid' => $decisionSupport->uuid(),
      'decisionSupportLabel' => $decisionSupport->label(),
      'processId' => $data['process_id'],
      'processLabel' => $process->getLabel(),
      'steps' => $processData['steps'],
      'isCompleted' => $decisionSupport->getIsCompleted(),
    ];
    $decisionSupport->setNewRevision(FALSE);
    $decisionSupport->setJsonString(json_encode($jsonstring));
    $decisionSupport->save();

    // Log the creation of the entity.
    $this->logger->notice('Created new DecisionSupport entity with ID @id.', ['@id' => $decisionSupport->id()]);
    return $decisionSupport;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDecisionSupport($decisionSupportId, array $data) {
    /** @var \Drupal\decision_support\Entity\DecisionSupport|null $decisionSupport */
    $decisionSupport = $this->entityTypeManager->getStorage('decision_support_entity')->load($decisionSupportId);

    if (!$decisionSupport) {
      throw new NotFoundHttpException(sprintf('DecisionSupport with ID %s was not found.', $decisionSupportId));
    }
    if (!array_key_exists('decisionSupportLabel', $data) || !array_key_exists('isCompleted', $data)) {
      throw new BadRequestHttpException('Missing required fields for decision support update');
    }
    $json_string = json_encode($data);
    $decisionSupport->setJsonString($json_string);
    $decisionSupport->setName($data['decisionSupportLabel']);
    $decisionSupport->setIsCompleted((bool) $data['isCompleted']);
    $decisionSupport->save();

    return $decisionSupport;
  }

  /**
   * {@inheritdoc}
   */
  public function archiveDecisionSupport($decisionSupportId) {

    /** @var \Drupal\decision_support\Entity\DecisionSupport|null $decisionSupport */
    $decisionSupport = $this->entityTypeManager->getStorage('decision_support_entity')->load($decisionSupportId);
    if (!$decisionSupport) {
      throw new NotFoundHttpException(sprintf('DecisionSupport with ID %s was not found.', $decisionSupportId));
    }

    $label = $decisionSupport->getName();
    $decisionSupport->setName('Archived - ' . $label);
    $decisionSupport->set('status', FALSE);
    $decisionSupport->setRevisionStatus('Archived');
    $decisionSupport->save();

    $this->logger->notice('Moved DecisionSupport with ID @id to archived.', ['@id' => $decisionSupportId]);

    return $decisionSupport;
  }

}
