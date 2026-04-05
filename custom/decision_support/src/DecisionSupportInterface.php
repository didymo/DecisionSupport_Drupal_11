<?php

declare(strict_types=1);

namespace Drupal\decision_support;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a decision support entity type.
 */
interface DecisionSupportInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
