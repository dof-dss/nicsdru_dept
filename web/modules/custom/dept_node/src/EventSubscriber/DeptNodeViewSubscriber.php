<?php

declare(strict_types=1);

namespace Drupal\dept_node\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dept_core\DepartmentManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event handler to process controller requests.
 */
final class DeptNodeViewSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly DepartmentManager $departmentManager,
    private readonly RouteMatchInterface $currentRouteMatch,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Handle nodes viewed on NIGov.
   */
  public function niGovNodeHandler(ControllerEvent $event) {
    // TODO: remove before flight (after testing by QA)
    return;

    if ($this->departmentManager->getCurrentDepartment()->id() !== 'nigov') {
      return;
    }

    $route_name = $this->currentRouteMatch->getRouteName();
    if ($route_name !== 'entity.node.canonical') {
      return;
    }

    $node = $this->currentRouteMatch->getParameter('node');

    if (!empty($node) && $node->hasField('field_domain_source')) {
      // For nodes with a domain source other than 'nigov', display Drupal’s 404 page.
      if (!$node->get('field_domain_source')->isEmpty() && $node->get('field_domain_source')->getString() !== 'nigov') {
        if ($this->currentUser->isAnonymous()) {
          throw new NotFoundHttpException();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::CONTROLLER => ['niGovNodeHandler', 100],
    ];
  }

}
