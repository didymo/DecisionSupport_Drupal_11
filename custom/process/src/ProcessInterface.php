<?php

declare(strict_types=1);

namespace Drupal\process;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a process entity type.
 */
interface ProcessInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
