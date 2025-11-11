<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Departmental sites: topics routes.
 */
final class TopicsDbApiDashboard extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly Connection $connection,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $content_original = $this->connection->schema()->tableExists('node__field_topic_content_original');
    $revision_original = $this->connection->schema()->tableExists('node_revision__field_topic_content_original');

    if ($content_original === FALSE || $revision_original === FALSE) {
      $build['content'] = [
        '#markup' => $this->t('Original tables not found.'),
      ];
    }

    $results = $this->connection->query("
      SELECT
    t1.entity_id,
    t1.revision_id,
    t1.field_topic_content_target_id,
    t1.delta,
    fd.title,
    'current' AS source_table
FROM node__field_topic_content t1
         LEFT JOIN node__field_topic_content_original t2
                   ON t1.entity_id = t2.entity_id
                       AND t1.revision_id = t2.revision_id
                       AND t1.field_topic_content_target_id = t2.field_topic_content_target_id
                       AND t1.delta = t2.delta
         LEFT JOIN node_field_data fd
                   ON t1.entity_id = fd.nid
WHERE t2.entity_id IS NULL

UNION ALL

SELECT
    t2.entity_id,
    t2.revision_id,
    t2.field_topic_content_target_id,
    t2.delta,
    fd.title,
    'original' AS source_table
FROM node__field_topic_content_original t2
         LEFT JOIN node__field_topic_content t1
                   ON t1.entity_id = t2.entity_id
                       AND t1.revision_id = t2.revision_id
                       AND t1.field_topic_content_target_id = t2.field_topic_content_target_id
                       AND t1.delta = t2.delta
         LEFT JOIN node_field_data fd
                   ON t2.entity_id = fd.nid
WHERE t1.entity_id IS NULL;
    ")->fetchAll();

    $rows = [];
    $parent_item = 0;
    $parent_count = 0;
    $dept_count = [];
    $result_count = 1;

    foreach ($results as $result) {
      $parent = $this->entityTypeManager()->getStorage('node')->load($result->entity_id);
      $child = $this->entityTypeManager()->getStorage('node')->load($result->field_topic_content_target_id);

      $new_state = match($child->get('moderation_state')->getString()) {
        'published' => ['label' => 'Archive', 'state' => 'archived'],
        'archived' =>  ['label' => 'Publish', 'state' => 'published'],
        default => NULL,
      };

      $rows[] = [
        'data' => [
          $result_count,
          [
            'data' => $result->source_table,
            'style' => $result->source_table === 'current' ? 'color: #65a30d;' : 'color: #ef4444;',
          ],
          ($parent->id() === $parent_item) ? '' : $parent->get('field_domain_source')->getString(),
          [
          'data' => $parent->id() === $parent_item ? '' : new FormattableMarkup('@author<br/> @bundle (@status)', [
            '@bundle' => ucfirst($parent->bundle()),
            '@author' => $parent->getOwner()->toLink()->toString(),
            '@status' => ucfirst($parent->get('moderation_state')->getString()),
          ])
          ],
          $parent->id() === $parent_item ? '' : $parent->toLink($parent->label())->toString(),
          [
            'data' => new FormattableMarkup('@author<br/> @bundle (@status)', [
              '@bundle' => ucfirst($child->bundle()),
              '@author' => $child->getOwner()->toLink()->toString(),
              '@status' => ucfirst($child->get('moderation_state')->getString()),
            ])
          ],
          $child->toLink($child->label())->toString(),
          [
          'data' => new FormattableMarkup('<ul><li>Entity ID: @entity_id</li> <li>Revision ID: @revision_id</li><li>Target ID: @target_id</li></ul>', [
            '@entity_id' => $result->entity_id,
            '@revision_id' => $result->revision_id,
            '@target_id' => $result->field_topic_content_target_id,
          ])
          ],
          [
            'data' => (is_null($new_state)) ? '' : Link::fromTextAndUrl(
              $new_state['label'] . ' child',
              Url::fromRoute('origins_workflow.moderation_state_controller_change_state', [
                'nid' => $child->id(),
                'new_state' => $new_state['state']
              ],
                [
                  'query' => [
                    'destination' => \Drupal::request()->getRequestUri()
                  ]
                ]
              )),
          ],
        ],
        'style' => $parent->id() !== $parent_item ? 'background-color: #cbd5e1;' : ''
      ];

      if ($parent->id() !== $parent_item) {
        $parent_count++;
        $dept_source = $parent->get('field_domain_source')->getString();
        if (array_key_exists($dept_source, $dept_count)) {
          $dept_count[$dept_source]++;
        }
        else {
          $dept_count[$dept_source] = 1;
        }
      }

      $parent_item = $parent->id();
      $result_count++;
    }

    $summary = 'Affected topics by dept: ';

    foreach ($dept_count as $dept => $count) {
      $summary .= '<strong>' . $dept . '</strong> (' . $count . ') - ';
    }

    $build['summary'] = [
      '#markup' => 'Totals: ' . $parent_count . ' topics, ' . count($results) . ' children. <br>' . rtrim($summary, ' -'),
    ];

    $build['content'] = [
      '#type' => 'table',
      '#header' => [
        '#',
        'Change',
        'Department',
        'Parent details',
        'Parent',
        'Child details',
        'Child',
        [
          'data' => 'Data',
          'style' => 'width: 250px'
        ],
        [
          'data' => 'Operations',
          'style' => 'width: 200px'
        ],
      ],
      '#rows' => $rows,
    ];

    return $build;
  }

}
