<?php

declare(strict_types=1);

namespace Drupal\decision_support_file;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a decision support file entity type.
 */
interface DecisionSupportFileInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
