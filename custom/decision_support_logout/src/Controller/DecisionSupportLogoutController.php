<?php

declare(strict_types=1);

namespace Drupal\decision_support_logout\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\decision_support_logout\Service\TokenRevoker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Handles OAuth logout requests.
 */
final class DecisionSupportLogoutController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly TokenRevoker $tokenRevoker,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    /** @var \Drupal\Core\Logger\LoggerChannelInterface $logger */
    $logger = $container->get('logger.channel.decision_support_logout');

    return new self(
      $container->get('decision_support_logout.token_revoker'),
      $logger,
    );
  }

  /**
   * Access callback for the logout endpoint.
   */
  public function access(AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($account->isAuthenticated())->cachePerUser();
  }

  /**
   * Revokes user tokens and logs out Drupal session.
   */
  public function logout(): JsonResponse {
    $account = $this->currentUser();
    $revoked_tokens = $this->tokenRevoker->revokeAllForAccount($account);

    // Ends the active Drupal session when present and resets current user.
    user_logout();

    $this->logger->notice('OAuth logout completed for uid {uid}; revoked {count} token(s).', [
      'uid' => $account->id(),
      'count' => $revoked_tokens,
    ]);

    return new JsonResponse([
      'status' => 'ok',
      'revoked_tokens' => $revoked_tokens,
      'drupal_logged_out' => TRUE,
    ]);
  }

}
