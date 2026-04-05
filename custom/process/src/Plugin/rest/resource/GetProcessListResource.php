<?php

declare(strict_types=1);

namespace Drupal\process\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\process\Services\ProcessService\ProcessServiceInterface;

/**
 * Represents Get Process List records as resources.
 *
 * @RestResource (
 *   id = "get_process_list_resource",
 *   label = @Translation("Get Process List"),
 *   uri_paths = {
 *     "canonical" = "/rest/process/list",
 *   }
 * )
 */
final class GetProcessListResource extends ResourceBase {

  /**
   * The current user.
   */
  private AccountProxyInterface $currentUser;

  /**
   * The process service.
   */
  private ProcessServiceInterface $processService;

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
    ProcessServiceInterface $process_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $currentUser;
    $this->processService = $process_service;
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
      $container->get('process.service')
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
    if (!$this->currentUser->hasPermission('view process')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Retrieve the list of processes.
      $processList = $this->processService->getProcessList();
      $response = new ResourceResponse($processList);
      $response->addCacheableDependency($this->currentUser);

      return $response;
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Log the error message.
      $this->logger->error('An error occurred while loading Process list: @message', ['@message' => $e->getMessage()]);

      // Throw a generic HTTP exception for internal server errors.
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
