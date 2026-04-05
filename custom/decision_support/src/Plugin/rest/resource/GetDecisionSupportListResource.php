<?php

declare(strict_types=1);

namespace Drupal\decision_support\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\decision_support\Services\DecisionSupport\DecisionSupportServiceInterface;

/**
 * Represents Decision Support Get List records as resources.
 *
 * @RestResource (
 *   id = "get_decision_support_list",
 *   label = @Translation("Decision Support Get List"),
 *   uri_paths = {
 *     "canonical" = "/rest/support/list",
 *   }
 * )
 */
final class GetDecisionSupportListResource extends ResourceBase {

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
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function get() {
    // Check user permissions.
    if (!$this->currentUser->hasPermission('view decision_support_entity')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Retrieve the list of decision.
      $decisionSupportList = $this->decisionSupportService->getDecisionSupportList();
      $response = new ResourceResponse($decisionSupportList);
      $response->addCacheableDependency($this->currentUser);

      return $response;
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Log the error message.
      $this->logger->error('An error occurred while loading decision support list: @message', ['@message' => $e->getMessage()]);

      // Throw a generic HTTP exception for internal server errors.
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
