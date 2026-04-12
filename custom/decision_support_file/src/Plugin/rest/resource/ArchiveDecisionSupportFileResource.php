<?php

declare(strict_types=1);

namespace Drupal\decision_support_file\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\decision_support_file\Services\DecisionSupportFile\DecisionSupportFileServiceInterface;

/**
 * Represents Decision Support File Archive records as resources.
 *
 * @RestResource (
 *   id = "archive_decision_support_file",
 *   label = @Translation("Decision Support File Archive"),
 *   uri_paths = {
 *     "canonical" = "/rest/support/file/archive/{fileId}",
 *     "patch" = "/rest/support/file/archive/{fileId}"
 *   }
 * )
 */
final class ArchiveDecisionSupportFileResource extends ResourceBase {

  /**
   * The current user.
   */
  private AccountProxyInterface $currentUser;

  /**
   * The decision support file service.
   */
  private DecisionSupportFileServiceInterface $decisionSupportFileService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $currentUser,
    DecisionSupportFileServiceInterface $decision_support_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $currentUser;
    $this->decisionSupportFileService = $decision_support_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('decision_support_file.service')
    );
  }

  /**
   * Responds to PATCH requests.
   */
  public function patch($fileId): ModifiedResourceResponse {
    // Angular: DocumentUploadService.archiveDecisionSupportDocument() — PATCH with no request body.
    // The file ID is passed via the URL only. Do not add a body parameter here.

    // Check user permissions.
    if (!$this->currentUser->hasPermission('delete decision_support_file')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Attempt to update the decision support file entity.
      $entity = $this->decisionSupportFileService->deleteDecisionSupportFile($fileId);
      $this->logger->notice('The Decision Support File @id has been moved to Archived.', ['@id' => $fileId]);

      // Return a response with status code 200 OK.
      return new ModifiedResourceResponse($entity, 200);
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Handle any unexpected errors during archive.
      $this->logger->error(
        'An error occurred while moving Decision Support File to archived: @message',
        ['@message' => $e->getMessage()]
      );
      throw new HttpException(500, 'Internal Server Error');
    }

  }

}
