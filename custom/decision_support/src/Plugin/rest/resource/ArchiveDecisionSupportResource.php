<?php

declare(strict_types=1);

namespace Drupal\decision_support\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\decision_support\Services\DecisionSupport\DecisionSupportServiceInterface;

/**
 * Represents Decision Support Archive records as resources.
 *
 * @RestResource (
 *   id = "archive_decision_support",
 *   label = @Translation("Decision Support Archive"),
 *   uri_paths = {
 *     "canonical" = "/rest/support/archive/{decisionSupportId}",
 *     "delete" = "/rest/support/archive/{decisionSupportId}"
 *   }
 * )
 */
final class ArchiveDecisionSupportResource extends ResourceBase {

  /**
   * The current user.
   */
  private AccountProxyInterface $currentUser;

  /**
   * The decision support service.
   */
  private DecisionSupportServiceInterface $decisionSupportService;

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
    DecisionSupportServiceInterface $decision_support_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $currentUser;
    $this->decisionSupportService = $decision_support_service;
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
      $container->get('decision_support.service')
    );
  }

  /**
   * Responds to DELETE requests.
   *
   * @param string $decisionSupportId
   *   The ID of the DecisionSupport entity to be archived.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the specified entity does not exist.
   */
  public function delete($decisionSupportId): ModifiedResourceResponse {
    // Check user permission.
    if (!$this->currentUser->hasPermission('delete decision_support_entity')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Archive the decision support entity.
      $entity = $this->decisionSupportService->archiveDecisionSupport($decisionSupportId);
      $this->logger->notice('The DecisionSupport @id has been moved to Archived.', ['@id' => $decisionSupportId]);
      return new ModifiedResourceResponse($entity, 200);
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Log the error message.
      $this->logger->error(
        'An error occurred while deleting DecisionSupport entity with ID @id: @message',
        ['@id' => $decisionSupportId, '@message' => $e->getMessage()]
      );
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
