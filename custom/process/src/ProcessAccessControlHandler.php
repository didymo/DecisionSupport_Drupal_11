<?php

declare(strict_types=1);

namespace Drupal\process;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the process entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class ProcessAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view process'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit process'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete process'),
      'delete revision' => AccessResult::allowedIfHasPermission($account, 'delete process revision'),
      'view all revisions', 'view revision' => AccessResult::allowedIfHasPermissions($account, ['view process revision', 'view process']),
      'revert' => AccessResult::allowedIfHasPermissions($account, ['revert process revision', 'edit process']),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create process', 'administer process'], 'OR');
  }

}
