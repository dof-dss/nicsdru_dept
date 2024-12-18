<?php

namespace Drupal\dept_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\Entity\Domain;
use Drupal\domain_access\DomainAccessManager;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Class to inspect url and return the absolute path
 * based on the group(s) the content is assigned to.
 */
class Rel2AbsUrl {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $deptManager;

  /**
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a new Rel2AbsUrl class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service object.
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   Department manager object.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   Alias manager service object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DepartmentManager $department_manager, AliasManagerInterface $alias_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->deptManager = $department_manager;
    $this->aliasManager = $alias_manager;
  }

  /**
   * @param string $url
   *   The relative url path (starts with /) to handle.
   * @param mixed|null $node
   *   Either a node entity object or a node id. Can also be empty if
   *   the URL is supplied, in which case a path alias lookup is performed
   *   and if it resolves to a node canonical path, we load that object.
   *
   * @return string|void
   *   Returns the absolute URL for a relative one, based on the first
   *   group assigned to that content. An exception is thrown if the URL
   *   and node parameters are both missing.
   */
  public function handleUrl(string $url = '', mixed $node = NULL) {
    if (empty($url) && empty($node)) {
      throw new \Exception("Url and node parameters are missing. Cannot perform a lookup without at least one.");
    }

    if (empty($node)) {
      // Attempt to load the node from the URL alias.
      $path = $this->aliasManager->getPathByAlias($url);
      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $node = $matches[1];
      }
    }

    /* is_numeric() tests for string numbers as well as true integers */
    if (is_numeric($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    if ($node instanceof NodeInterface) {
      $domain_id = DomainAccessManager::getAccessValues($node, 'field_domain_access');

      if (!empty($domain_id)) {
        /** @var $domain \Drupal\domain\Entity\Domain */
        // @phpstan-ignore-next-line
        $domain = $this->entityTypeManager->getStorage('domain')->load(array_key_first($domain_id));
        // @phpstan-ignore-next-line
        if (str_starts_with($url, '/')) {
          $url = ltrim($url, '/');
        }

        return $domain->getPath() . $url;
      }

      // Return the original link if we can't process it.
      return 'https://' . $url;
    }
  }

}
