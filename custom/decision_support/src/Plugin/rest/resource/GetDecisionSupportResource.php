<?php

declare(strict_types=1);

namespace Drupal\decision_support\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\decision_support\Services\DecisionSupport\DecisionSupportServiceInterface;

/**
 * Represents Decision Support Get records as resources.
 *
 * @RestResource (
 *   id = "get_decision_support",
 *   label = @Translation("Decision Support Get"),
 *   uri_paths = {
 *     "canonical" = "/rest/support/get/{decisionSupportId}",
 *   }
 * )
 */
final class GetDecisionSupportResource extends ResourceBase {

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
   * Responds to GET requests.
   */
  public function get($decisionSupportId): JsonResponse {
    // Check user permissions.
    if (!$this->currentUser->hasPermission('view decision_support_entity')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Retrieve the decision support data.
      $decisionSupportJsonString = $this->decisionSupportService->getDecisionSupport($decisionSupportId);

      // Return the JSON response.
      return new JsonResponse($decisionSupportJsonString, 200, [], TRUE);
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Log the error message.
      $this->logger->error('An error occurred while loading DecisionSupport: @message', ['@message' => $e->getMessage()]);

      // Throw a generic HTTP exception for internal server errors.
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
