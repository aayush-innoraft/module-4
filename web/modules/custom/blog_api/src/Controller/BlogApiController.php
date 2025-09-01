<?php

namespace Drupal\blog_api\Controller;

use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for Blog API.
 */
class BlogApiController extends ControllerBase {

  /**
   * Returns JSON list of published Blog nodes with optional filters.
   */
  public function list(Request $request): JsonResponse {
    $config = $this->config('blog_api.settings');

    // Admin config filters.
    $from = $config->get('from_date');
    $to = $config->get('to_date');
    $author_ids = $config->get('author_uids') ?: [];
    $tag_ids = $config->get('tag_tids') ?: [];

    // API query parameters can override admin config (optional)
    $from_param = $request->query->get('from') ?: $from;
    $to_param = $request->query->get('to') ?: $to;
    $authors_param = $request->query->get('authors')
      ? array_map('intval', explode(',', $request->query->get('authors')))
      : $author_ids;
    $tags_param = $request->query->get('tags')
      ? array_map('intval', explode(',', $request->query->get('tags')))
      : $tag_ids;

    // Build entity query.
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'blogs')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE);

    if ($from_param) {
      $query->condition('created', strtotime($from_param), '>=');
    }
    if ($to_param) {
      $query->condition('created', strtotime($to_param . ' 23:59:59'), '<=');
    }
    if (!empty($authors_param)) {
      $query->condition('uid', $authors_param, 'IN');
    }
    if (!empty($tags_param)) {
      $query->condition('field_blog_tags.target_id', $tags_param, 'IN');
    }

    $nids = $query->execute();
    $nodes = Node::loadMultiple($nids);

    $items = [];
    foreach ($nodes as $node) {
      /** @var \Drupal\node\NodeInterface $node */
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
        'author' => $node->getOwner()?->getDisplayName(),
        'tags' => $tag_labels,
      ];
    }

    return new JsonResponse([
      'count' => count($items),
      'results' => $items,
    ]);
  }

}
