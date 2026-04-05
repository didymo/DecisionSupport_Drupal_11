<?php

declare(strict_types=1);

namespace Drupal\process\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\process\Services\ProcessService\ProcessServiceInterface;

/**
 * Represents Duplicate Process records as resources.
 *
 * @RestResource (
 *   id = "duplicate_process_resource",
 *   label = @Translation("Duplicate Process"),
 *   uri_paths = {
 *     "create" = "/rest/process/duplicate",
 *   }
 * )
 */
final class DuplicateProcessResource extends ResourceBase {

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
   * Responds to POST requests and saves the new record.
   *
   * @param array $data
   *   The data to create the new duplicated process entity.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing the created entity.
   */
  public function post(array $data): ModifiedResourceResponse {
    // Check user permissions.
    if (!$this->currentUser->hasPermission('create process')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Create the duplicate process entity.
      $entity = $this->processService->duplicateProcess($data);

      // Return a response with status code 201 Created.
      return new ModifiedResourceResponse($entity, 201);
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Handle any exceptions that occur during entity creation.
      $this->logger->error('An error occurred while duplicating Process entity: @message', ['@message' => $e->getMessage()]);
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
