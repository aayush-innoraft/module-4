<?php

namespace Drupal\blog_tools\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Related Blogs Block.
 *
 * @Block(
 *   id = "related_blogs_block",
 *   admin_label = @Translation("Related Blogs"),
 * )
 */
class RelatedBlogsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs a new RelatedBlogsBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * Builds the block content.
   */
  public function build() {
    $build = [];

    $current_node = $this->routeMatch->getParameter('node');

    if ($current_node instanceof NodeInterface && $current_node->bundle() === 'blogs') {
      $author_id = $current_node->getOwnerId();
      $current_nid = $current_node->id();

      $query = $this->entityTypeManager->getStorage('node')->getQuery();
      $nids = $query
        ->accessCheck(TRUE)
        ->condition('type', 'blogs')
        ->condition('status', 1)
        ->condition('uid', $author_id)
        ->condition('nid', $current_nid, '!=')
        ->sort('field_likes', 'DESC')
        ->range(0, 3)
        ->execute();

      $related_blogs = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

      if (!empty($related_blogs)) {
        $items = [];
        foreach ($related_blogs as $blog) {
          $title = $blog->label();
          $url = $blog->toUrl()->toString();
          $likes = $blog->get('field_likes')->value ?? 0;

          $items[] = [
            '#markup' => "<a href='$url'>$title</a> ({$likes} likes)",
          ];
        }

        $build['related_blogs_block'] = [
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => $this->t('Related Blogs by Author'),
          'items' => [
            '#theme' => 'item_list',
            '#items' => $items,
          ],
        ];
      }
      else {
        $build['related_blogs_block'] = [
          '#markup' => $this->t('No related blogs found.'),
        ];
      }
    }
    else {
      $build['related_blogs_block'] = [
        '#markup' => $this->t('No related blogs found.'),
      ];
    }

    return $build;
  }

}
