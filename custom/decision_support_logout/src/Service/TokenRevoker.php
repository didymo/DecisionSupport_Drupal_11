<?php

declare(strict_types=1);

namespace Drupal\decision_support_logout\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Revokes OAuth2 tokens for an account.
 */
final class TokenRevoker {

  /**
   * Constructs a TokenRevoker object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Revokes all OAuth2 tokens associated with the account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being logged out.
   *
   * @return int
   *   Number of token entities deleted.
   */
  public function revokeAllForAccount(AccountInterface $account): int {
    $uid = (int) $account->id();
    if ($uid <= 0) {
      return 0;
    }

    $token_storage = $this->entityTypeManager->getStorage('oauth2_token');
    $consumer_storage = $this->entityTypeManager->getStorage('consumer');

    $token_ids = [];

    // Revoke tokens where this user is the authenticated subject.
    $subject_query = $token_storage->getQuery();
    $subject_query->accessCheck(FALSE);
    $subject_token_ids = $subject_query
      ->condition('auth_user_id', $uid)
      ->execute();
    if (!empty($subject_token_ids)) {
      $token_ids = array_merge($token_ids, array_values($subject_token_ids));
    }

    // Revoke tokens issued to consumers that use this user as default owner.
    $consumers = $consumer_storage->loadByProperties(['user_id' => $uid]);
    if (!empty($consumers)) {
      $consumer_ids = array_map(static fn ($consumer): string => (string) $consumer->id(), $consumers);
      $client_query = $token_storage->getQuery();
      $client_query->accessCheck(FALSE);
      $client_token_ids = $client_query
        ->condition('client', $consumer_ids, 'IN')
        ->execute();
      if (!empty($client_token_ids)) {
        $token_ids = array_merge($token_ids, array_values($client_token_ids));
      }
    }

    if (empty($token_ids)) {
      return 0;
    }

    $token_ids = array_values(array_unique($token_ids));
    $tokens = $token_storage->loadMultiple($token_ids);
    if (empty($tokens)) {
      return 0;
    }

    $deleted_count = count($tokens);
    $token_storage->delete($tokens);
    return $deleted_count;
  }

}
