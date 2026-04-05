<?php

declare(strict_types=1);

namespace Drupal\decision_support_file\Services\DecisionSupportFile;

use Drupal\decision_support_file\Entity\DecisionSupportFile;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service class for handling decision support files.
 */
final class DecisionSupportFileService implements DecisionSupportFileServiceInterface {

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a DecisionSupportFileService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function getDecisionSupportFile($decisionSupportId) {
    // Create an entity query for the decision_support_file entity.
    $query = $this->entityTypeManager
    // Get the storage handler.
      ->getStorage('decision_support_file')
    // Create the query.
      ->getQuery()
    // Add condition for published files.
      ->condition('status', 1)
    // Add condition for matching decisionSupportId.
      ->condition('decisionSupportId', $decisionSupportId)
    // Enable access checks.
      ->accessCheck(TRUE);

    $decisionSupportFileIds = $query->execute();
    $this->logger->info('Query result for Decision Support Id: {id}, File IDs: {file_ids}', [
      'id' => $decisionSupportId,
      'file_ids' => json_encode($decisionSupportFileIds),
    ]);
    $unformattedDecisionSupportFile = $this->entityTypeManager->getStorage('decision_support_file')->loadMultiple($decisionSupportFileIds);
    $decisionSupportFileList = [];

    foreach ($unformattedDecisionSupportFile as $unformattedDecisionSupportFile) {
      if ($unformattedDecisionSupportFile instanceof DecisionSupportFile) {
        if ($unformattedDecisionSupportFile->getVisible()) {
          $file['label'] = $unformattedDecisionSupportFile->getLabel();
          $file['entityId'] = $unformattedDecisionSupportFile->id();
          $file['stepId'] = $unformattedDecisionSupportFile->getStepId();
          $file['fileEntityId'] = $unformattedDecisionSupportFile->getFileId();
          $file['isVisible'] = $unformattedDecisionSupportFile->getVisible();

          $decisionSupportFileList[] = $file;
        }
      }
    }

    return $decisionSupportFileList;
  }

  /**
   * {@inheritdoc}
   */
  public function createDecisionSupportFile(array $data) {
    // Validate required fields.
    if (empty($data['stepId']) || empty($data['decisionSupportId']) || empty($data['fid'])) {
      throw new BadRequestHttpException('Missing required fields');
    }

    // Load the file entity using the provided fid.
    $file_entity = $this->entityTypeManager->getStorage('file')->load($data['fid']);
    if (!$file_entity) {
      throw new NotFoundHttpException('File not found');
    }
    if (!$file_entity->access('view', $this->currentUser)) {
      throw new AccessDeniedHttpException('Access denied to the specified file.');
    }

    // Create new DecisionSupportFile entity.
    $decision_support_file = DecisionSupportFile::create([
      'label' => $data['label'] ?? $file_entity->getFilename(),
      'notes' => $data['notes'] ?? '',
      'stepId' => $data['stepId'],
      'decisionSupportId' => $data['decisionSupportId'],
      'visible' => $data['visible'] ?? TRUE,
      'file' => [
        'target_id' => $file_entity->id(),
      ],
    ]);

    $decision_support_file->save();

    return $decision_support_file;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDecisionSupportFile($fileId) {

    /** @var \Drupal\decision_support_file\Entity\DecisionSupportFile|null $decisionSupportFile */
    $decisionSupportFile = $this->entityTypeManager->getStorage('decision_support_file')->load($fileId);

    if (!$decisionSupportFile) {
      throw new NotFoundHttpException();
    }
    $label = $decisionSupportFile->getLabel();
    $newLabel = "$label - Archived";
    $decisionSupportFile->setLabel($newLabel);
    $decisionSupportFile->setVisible(FALSE);
    $decisionSupportFile->save();

    return $decisionSupportFile;
  }

}
