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
 * Represents Decision Support Patch records as resources.
 *
 * @RestResource (
 *   id = "patch_decision_support",
 *   label = @Translation("Decision Support Patch"),
 *   uri_paths = {
 *     "canonical" = "/rest/support/update/{decisionSupportId}",
 *     "patch" = "/rest/support/update/{decisionSupportId}"
 *   }
 * )
 */
final class PatchDecisionSupportResource extends ResourceBase {

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
   * Responds to PATCH requests.
   *
   * @param int $decisionSupportId
   *   The ID of the decision support entity to update.
   * @param array $data
   *   The data to update the decision support entity with.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The modified resource response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Thrown when an error occurs during the update.
   */
  public function patch($decisionSupportId, array $data): ModifiedResourceResponse {

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('edit decision_support_entity')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Attempt to update the decision support entity.
      $entity = $this->decisionSupportService->updateDecisionSupport($decisionSupportId, $data);
      $this->logger->notice('The DecisionSupport @id has been updated.', ['@id' => $decisionSupportId]);

      // Return a response with status code 200 OK.
      return new ModifiedResourceResponse($entity, 200);
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Handle any other exceptions that occur during the update.
      $this->logger->error('An error occurred while updating DecisionSupport: @message', ['@message' => $e->getMessage()]);
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
