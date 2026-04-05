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
 * Represents Decision Support Post records as resources.
 *
 * @RestResource (
 *   id = "post_decision_support",
 *   label = @Translation("Decision Support Post"),
 *   uri_paths = {
 *     "create" = "/rest/support/post",
 *   }
 * )
 */
final class PostDecisionSupportResource extends ResourceBase {

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
   * Responds to POST requests and saves the new record.
   *
   * @param array $data
   *   The data to create the new decision support entity.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing the created entity.
   */
  public function post(array $data): ModifiedResourceResponse {
    // Check user permissions.
    if (!$this->currentUser->hasPermission('create decision_support_entity')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Create the new decision support entity.
      $entity = $this->decisionSupportService->createDecisionSupport($data);

      // Return a response with status code 201 Created.
      return new ModifiedResourceResponse($entity, 201);
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Log the error message.
      $this->logger->error('An error occurred while creating Decision Support entity: @message', ['@message' => $e->getMessage()]);

      // Throw a generic HTTP exception for internal server errors.
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
