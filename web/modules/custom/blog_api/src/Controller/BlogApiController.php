<?php

namespace Drupal\blog_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;

/**
 * Controller for Blog API.
 */
class BlogApiController extends ControllerBase {

  /**
   * Returns a JSON list of published Blogs nodes with optional filters.
   */
  public function list() {
    $config = $this->config('blog_api.settings');

    // Build entity query for Blogs.
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
    // Machine name of your content type.
      ->condition('type', 'blogs')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE);

    // Date range filtering.
    $from = $config->get('from_date');
    $to = $config->get('to_date');
    if ($from || $to) {
      if ($from) {
        $query->condition('created', strtotime($from), '>=');
      }
      if ($to) {
        $query->condition('created', strtotime($to . ' 23:59:59'), '<=');
      }
    }

    // Filter by authors (array of user IDs).
    $authors = array_filter((array) $config->get('author_uids') ?: []);
    if (!empty($authors)) {
      $query->condition('uid', $authors, 'IN');
    }

    // Filter by tags (array of term IDs) — using field_blog_tags.
    $tags = array_filter((array) $config->get('tag_tids') ?: []);
    $temp_node = Node::create(['type' => 'blogs']);
    if (!empty($tags) && $temp_node->hasField('field_blog_tags')) {
      $query->condition('field_blog_tags.target_id', $tags, 'IN');
    }

    // Load nodes.
    $nids = $query->execute();
    $nodes = Node::loadMultiple($nids);

    $items = [];
    foreach ($nodes as $node) {
      /** @var \Drupal\node\NodeInterface $node */

      // Collect tag labels safely from field_blog_tags.
      $tag_labels = [];
      if ($node->hasField('field_blog_tags')) {
        foreach ($node->get('field_blog_tags') as $tag_item) {
          if ($tag_item->entity) {
            $tag_labels[] = $tag_item->entity->label();
          }
        }
      }

      $items[] = [
        'title' => $node->label(),
        'body' => $node->hasField('body') ? ($node->get('body')->summary ?: $node->get('body')->value) : '',
        'published_date' => date('c', (int) $node->getCreatedTime()),
        'author' => $node->getOwner() ? $node->getOwner()->getDisplayName() : NULL,
        'tags' => $tag_labels,
      ];
    }

    return new JsonResponse([
      'count' => count($items),
      'results' => $items,
    ]);
  }

}
