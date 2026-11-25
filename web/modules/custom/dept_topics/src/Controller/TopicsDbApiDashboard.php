<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays a dashboard of topics and content that are not in sync.
 */
final class TopicsDbApiDashboard extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly Connection $connection,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('renderer'),
    );
  }

  /**
   * Builds the dashboard.
   */
  public function __invoke(): array {
    $content_original = $this->connection->schema()->tableExists('node__field_topic_content_original');
    $revision_original = $this->connection->schema()->tableExists('node_revision__field_topic_content_original');

    if ($content_original === FALSE || $revision_original === FALSE) {
      $build['content'] = [
        '#markup' => $this->t('Original tables not found.'),
      ];
      return $build;
    }

    // Select all entities with mismatched topic references.
    // Compares the previous values to the new updated references for each node.
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
    $row_counter = 1;

    foreach ($results as $result) {
      $parent = $this->entityTypeManager()->getStorage('node')->load($result->entity_id);
      $child = $this->entityTypeManager()->getStorage('node')->load($result->field_topic_content_target_id);
      $child_site_topics = [];

      // Mapping for our change status links.
      $new_state = match($child->get('moderation_state')->getString()) {
        'published' => ['label' => 'Archive', 'state' => 'archived'],
        'archived' =>  ['label' => 'Publish', 'state' => 'published'],
        default => NULL,
      };

      // Map domains to production URL's for the 'change state' links.
      $production_url = match($child->get('field_domain_source')->getString()) {
        'nigov' => 'https://www.northernireland.gov.uk/',
        'executiveoffice' => 'https://www.executiveoffice-ni.gov.uk/',
        'daera' => 'https://www.daera-ni.gov.uk/',
        'communities' => 'https://www.communities-ni.gov.uk/',
        'education' => 'https://www.education-ni.gov.uk/',
        'economy' => 'https://www.economy-ni.gov.uk/',
        'finance' => 'https://www.finance-ni.gov.uk/',
        'infrastructure' => 'https://www.infrastructure-ni.gov.uk/',
        'health' => 'https://www.health-ni.gov.uk/',
        'justice' => 'https://www.justice-ni.gov.uk/',
        default => NULL,
      };

      // Generate an inline list of site topics for the node.
      $site_topic_entities = $child->get('field_site_topics')->referencedEntities();

      foreach ($site_topic_entities as $site_topic_entity) {
        $child_site_topics[] = $site_topic_entity->toLink()->toString();
      }

      $child_site_topics_markup = [
        '#markup' => implode(', ', $child_site_topics),
      ];

      // Create production site links.
      if (!empty($production_url)) {
        $child_links = [
          '#theme' => 'item_list',
          '#items' => [
            Link::fromTextAndUrl('View child', Url::fromUri($production_url . ltrim($child->toUrl()->toString(), '/'), [
              'attributes' => ['target' => '_blank']
            ])),
          ],
        ];

        // We want to change the node state on production before updating to the
        // new topics system. This link opens the moderation state change url on
        // the correct department and once the status is changed, redirects to
        // the changed node.
        if (!empty($new_state)) {
          $child_links['#items'][] = Link::fromTextAndUrl($new_state['label'] . ' child', Url::fromUri($production_url . ltrim(Url::fromRoute('origins_workflow.moderation_state_controller_change_state', [
              'nid' => $child->id(),
              'new_state' => $new_state['state']
            ],
              [
                'query' => [
                  'destination' => $child->toUrl()->toString(),
                ],
              ],
            )->toString(), '/'),
            [
              'attributes' => ['target' => '_blank']
            ]));
        }
      }

      $rows[] = [
        'data' => [
          [
            'data' => new FormattableMarkup('<a id="row-@counter"></a>@counter', [
              '@counter' => $row_counter,
            ])
          ],
          [
            'data' => $result->source_table,
            'style' => $result->source_table === 'current' ? 'color: #65a30d;' : 'color: #ef4444;',
          ],
          ($parent->id() === $parent_item) ? '' : $parent->get('field_domain_source')->getString(),
          [
          'data' => $parent->id() === $parent_item ? '' : new FormattableMarkup('<span style="font-size: 1.15em;">@link</span><br>@status @bundle by @author', [
            '@link' => $parent->toLink($parent->label())->toString(),
            '@bundle' => ucfirst($parent->bundle()),
            '@author' => $parent->getOwner()->toLink()->toString(),
            '@status' => ucfirst($parent->get('moderation_state')->getString()),
          ])
          ],
          [
            'data' => new FormattableMarkup('<span style="font-size: 1.15em;">@link</span><br/>@status @bundle by @author<br><small>Created: @created -- Updated: @updated<br/>Topics: @topics</small>', [
              '@link' => $child->toLink($child->label())->toString(),
              '@bundle' => ucfirst($child->bundle()),
              '@author' => $child->getOwner()->toLink()->toString(),
              '@status' => ucfirst($child->get('moderation_state')->getString()),
              '@created' => date('d/m/Y', (int) $child->getCreatedTime()),
              '@updated' => date('d/m/Y', (int) $child->getChangedTime()),
              '@topics' => $this->renderer->render($child_site_topics_markup),
            ])
          ],

          [
          'data' => new FormattableMarkup('<ul><li>Entity ID: @entity_id</li> <li>Revision ID: @revision_id</li><li>Target ID: @target_id</li></ul>', [
            '@entity_id' => $result->entity_id,
            '@revision_id' => $result->revision_id,
            '@target_id' => $result->field_topic_content_target_id,
          ])
          ],
          [
            'data' => $this->renderer->render($child_links)
          ],
        ],
        'style' => $parent->id() !== $parent_item ? 'background-color: #cbd5e1;' : ''
      ];

      // Update our stats.
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
      $row_counter++;
    }

    $summary = 'Affected topics by dept: ';

    foreach ($dept_count as $dept => $count) {
      $summary .= '<strong>' . $dept . '</strong> (' . $count . ') - ';
    }

    $build['summary'] = [
      '#markup' => 'Totals: ' . $parent_count . ' topics, ' . count($results) . ' children. <br>' . rtrim($summary, ' -'),
    ];

    $build['notice'] = [
      '#markup' => '<h6>IMPORTANT: To change a child state on production you must be authenticated on each live department site.</h6>',
    ];

    $build['content'] = [
      '#type' => 'table',
      '#header' => [
        '#',
        'Change',
        'Department',
        'Parent',
        'Child',
        [
          'data' => 'Data',
          'style' => 'width: 250px'
        ],
        [
          'data' => 'Operations (PRODUCTION)',
          'style' => 'width: 200px'
        ],
      ],
      '#rows' => $rows,
    ];

    return $build;
  }

}
