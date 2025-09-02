<?php

namespace Drupal\blog_features\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;

/**
 * Provides a 'Related Blogs' Block.
 *
 * @Block(
 *   id = "related_blogs_block",
 *   admin_label = @Translation("Related Blogs"),
 * )
 */
class RelatedBlogsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RelatedBlogsBlock instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Get current node from the route.
    $node = \Drupal::routeMatch()->getParameter('node');

    if ($node instanceof Node && $node->bundle() === 'blogs') {
      $current_nid = $node->id();
      $author_id = $node->getOwnerId();

      // Query published blog nodes by the same author, excluding current node.
      $query = $this->entityTypeManager->getStorage('node')->getQuery();
      $nids = $query
        ->accessCheck(TRUE)
        ->condition('type', 'blogs')
        ->condition('status', 1)
        ->condition('uid', $author_id)
        ->condition('nid', $current_nid, '<>')
      // Ensure this matches your field machine name.
        ->sort('field_likes', 'DESC')
        ->range(0, 3)
        ->execute();

      $nodes = Node::loadMultiple($nids);

      if (!empty($nodes)) {
        $items = [];
        foreach ($nodes as $related_node) {
          $items[] = [
            '#type' => 'link',
            '#title' => $related_node->label(),
            '#url' => $related_node->toUrl(),
          ];
        }

        $build['related_blogs'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Related Blogs'),
          '#items' => $items,
          '#attributes' => ['class' => ['related-blogs']],
        ];
      }
      else {
        $build['related_blogs'] = [
          '#markup' => $this->t('No related blogs found.'),
        ];
      }
    }

    return $build;
  }

}
