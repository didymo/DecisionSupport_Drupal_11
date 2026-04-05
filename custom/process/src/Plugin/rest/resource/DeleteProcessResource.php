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
 * Represents Delete Process records as resources.
 *
 * @RestResource (
 *   id = "delete_process_resource",
 *   label = @Translation("Delete Process"),
 *   uri_paths = {
 *     "canonical" = "/rest/process/delete/{processId}",
 *     "patch" = "/rest/process/delete/{processId}"
 *   }
 * )
 */
final class DeleteProcessResource extends ResourceBase {

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
   * Responds to Patch requests.
   *
   * @param string $processId
   *   The ID of the process entity to be archived.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Thrown when an error occurs during the process.
   */
  public function patch($processId): ModifiedResourceResponse {
    // Check user permissions.
    if (!$this->currentUser->hasPermission('delete process')) {
      throw new AccessDeniedHttpException();
    }

    try {
      // Attempt to update the process entity.
      $entity = $this->processService->deleteProcess($processId);
      $this->logger->notice('The Process @id has been moved to Archived.', ['@id' => $processId]);

      // Return a response with status code 200 OK.
      return new ModifiedResourceResponse($entity, 200);
    }
    catch (HttpExceptionInterface $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      // Handle any unexpected errors during archive.
      $this->logger->error(
        'An error occurred while moving Process to archived: @message',
        ['@message' => $e->getMessage()]
      );
      throw new HttpException(500, 'Internal Server Error');
    }
  }

}
