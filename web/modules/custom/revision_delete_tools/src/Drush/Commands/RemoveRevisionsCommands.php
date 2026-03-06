<?php

namespace Drupal\revision_delete_tools\Drush\Commands;

use Drupal\revision_delete_tools\Plugin\QueueWorker\RemoveRevisions;
use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\revision_delete_tools\Services\RemoveRevisionsService;

/**
 * Command to queue entities for revision removal.
 */
final class RemoveRevisionsCommands extends DrushCommands {

  public function __construct(
    private readonly RemoveRevisionsService $removeRevisionsService,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('revision_delete_tools.remove_revisions_service'),
    );
  }

  /**
   * Queues revisions for removal.
   */
  #[CLI\Command(name: 'rdt:remove-revisions')]
  #[CLI\Argument(name: 'entityType', description: 'The entity type (e.g. node, media).')]
  #[CLI\Argument(name: 'bundle', description: 'The bundle name (e.g. page for nodes, image for media).')]
  #[CLI\Argument(name: 'entityId', description: 'The specific entity ID (optional).')]
  #[CLI\Option(name: 'keep', description: 'The number of revisions to keep (optional, default is 3).')]
  #[CLI\Usage(name: 'drush rdt:remove-revisions', description: 'Queue revisions for all revisionable entities.')]
  #[CLI\Usage(name: 'drush rdt:remove-revisions node', description: 'Queue revisions for all entities of the specified type.')]
  #[CLI\Usage(name: 'drush rdt:remove-revisions node page --keep=5', description: 'Queue revisions for all "page" nodes and keep 5 revisions.')]
  #[CLI\Usage(name: 'drush rdt:remove-revisions node page 123 --keep=3', description: 'Queue revisions for node with ID 123 under "page" and keep 3 revisions.')]
  public function queueRemoveRevisions(?string $entityType = NULL, ?string $bundle = NULL, ?string $entityId = NULL, int $keep = RemoveRevisions::REVISIONS_TO_KEEP): void {
    if ($keep < 1) {
      $this->io()->error("At least the default revision must be kept");
      return;
    }

    $revisionableEntityTypes = $this->removeRevisionsService->getRevisionableEntityTypes();
    if ($entityType && !in_array($entityType, $revisionableEntityTypes)) {
      $this->io()->error("Entity type <info>$entityType</info> is not revisionable.");
      return;
    }

    // Prompt for confirmation unless -y is used.
    $this->io()->title('Queueing Revisions for Deletion');
    if ($entityType) {
      $this->io()->writeln("Entity Type: <info>$entityType</info>");
    }
    else {
      foreach ($revisionableEntityTypes as $revisionableEntityType) {
        $this->io()->writeln("Entity Type: <info>$revisionableEntityType</info>");
      }
    }
    if ($bundle) {
      $this->io()->writeln("Bundle: <info>$bundle</info>");
    }
    if ($entityId) {
      $this->io()->writeln("Entity ID: <info>$entityId</info>");
    }
    $this->io()->writeln("Keeping <info>$keep</info> revisions.");

    // Confirmation prompt.
    if (!$this->io()->confirm("Are you sure you want to queue revisions for deletion?", TRUE)) {
      $this->io()->warning('Operation cancelled.');
      return;
    }

    // Queue a specific entity.
    if ($entityId) {
      $this->removeRevisionsService->queueRevisionsByEntityId($entityType, $entityId, $keep);
      $this->io()->success("Queued entity ID $entityId (bundle: $bundle).");
    }
    // Queue all entities of the specified bundle.
    elseif ($bundle) {
      $this->removeRevisionsService->queueRevisionsByBundle($entityType, $bundle, $keep);
      $this->io()->success("Queued all entities in bundle $bundle.");
    }
    // Queue all bundles of the given entity type.
    elseif ($entityType) {
      $this->removeRevisionsService->queueRevisionsByType($entityType, $keep);
      $this->io()->success("Queued all entities of type $entityType.");
    }
    // Queue all revisionable entity types.
    else {
      foreach ($revisionableEntityTypes as $revisionableEntityType) {
        $this->removeRevisionsService->queueRevisionsByType($revisionableEntityType, $keep);
        $this->io()->success("Queued all entities of type $revisionableEntityType.");
      }
    }
  }

}
