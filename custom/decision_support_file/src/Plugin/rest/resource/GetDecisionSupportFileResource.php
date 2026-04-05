<?php

declare(strict_types=1);

namespace Drupal\decision_support_file\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\decision_support_file\Services\DecisionSupportFile\DecisionSupportFileServiceInterface;

/**
 * Represents Decision Support File Get records as resources.
 *
 * @RestResource (
 *   id = "get_decision_support_file",
 *   label = @Translation("Decision Support File Get"),
 *   uri_paths = {
 *     "canonical" = "/rest/support/file/get/{decisionSupportId}",
 *   }
 * )
 */
final class GetDecisionSupportFileResource extends ResourceBase {

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
    DecisionSupportFileServiceInterface $decision_support_file_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $currentUser;
    $this->decisionSupportFileService = $decision_support_file_service;
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
   * Responds to GET requests.
   */
  public function get($decisionSupportId) {
    // Check user permissions.
    if (!$this->currentUser->hasPermission('view decision_support_file')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Retrieve the list of processes.
      $decisionSupportFile = $this->decisionSupportFileService->getDecisionSupportFile($decisionSupportId);
      $response = new ResourceResponse($decisionSupportFile);
      $response->addCacheableDependency($this->currentUser);

      return $response;
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Log the error message.
      $this->logger->error('An error occurred while loading Decision Support File list: @message', ['@message' => $e->getMessage()]);

      // Throw a generic HTTP exception for internal server errors.
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
