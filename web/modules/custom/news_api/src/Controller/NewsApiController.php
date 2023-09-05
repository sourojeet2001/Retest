<?php

namespace Drupal\news_api\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * This controller is used to fetch the data from News API.
 */
class NewsApiController extends ControllerBase {
  /**
   * This is used to store the data of all news type nodes.
   *
   * @var array
   */
  protected array $nodeData;

  protected object $connection;

  protected object $config;

  /**
   * This is store the user entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $user;

  /**
   * Constructs the CustomAPI object with the required depenency.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   This is the EntityTypeManagerInterface.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, Connection $connection, ConfigFactoryInterface $config) {
    $this->nodeData = $entity_manager->getStorage('node')->loadByProperties([
      'type' => 'news',
    ]);
    $this->connection = $connection;
    $this->config = $config;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('config.factory'),
    );
  }

  /**
   * This is to build the response for the api call.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Returns the JsonResponse.
   */
  public function buildResult(Request $request) {
    $authkey = $this->config('news_api.settings')->get('authkey');
    if ($request->headers->get('auth') == $authkey && $request->isMethod('GET')) {
      $tag_name = $request->get('tag');
      $node_data_array = [];
      $node_data_array['title'] = 'News Node Data';

      foreach ($this->nodeData as $node) {
        $tags = [];
        foreach ($node->get('field_news_category')->referencedEntities() as $tag) {
          $tags[] = $tag->label();
        }
        $image = $node->get('field_news_images');
        $newimage = [];
        foreach ($image as $singleimage) {
          $newimage[] = $singleimage->getValue();
        }
        $imagedata = [];
        foreach ($newimage as $imageprop) {
          $target_file = File::load($imageprop['target_id']);
          $imagedata[] = [
            'title' => $imageprop['title'],
            'alt' => $imageprop['alt'],
            'height' => $imageprop['height'],
            'width' => $imageprop['width'],
            'target_id' => $imageprop['target_id'],
            'url' => $target_file ? 'http://retest.com' . $target_file->createFileUrl() : '',
          ];
        }

        $database = $this->connection;
        $viewcount = $database->select('nodeviewcount', 'nvc')
          ->fields('nvc', ['count'])
          ->condition('nvc.nid', $node->id())
          ->execute()->fetchCol();

        $node_data_array['data'][] = [
          'title' => $node->label(),
          'body' => $node->get('body')->value,
          'summary' => $node->get('body')->summary,
          'image' => $imagedata,
          'published_date' => date('Y-m-d', $node->get('published_at')->value),
          'tags' => $tags,
          'viewcount' => $viewcount,
        ];
      }

      if ($tag_name) {
        $result = $this->tagBased($node_data_array, $tag_name);
      }

      if ($result) {
        $response = new JsonResponse($result, 200);
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return $response;
      }
      else {
        return new JsonResponse("No news for the Tag was found.", 200);
      }
    }
    else {
      return new JsonResponse("Forbidden", 403);
    }
  }

  /**
   * This method is to filter the data based on tags.
   *
   * @param array $node_data_array
   *   The input data array.
   * @param string $tag_name
   *   The tag name.
   *
   * @return array
   *   Returns the result array.
   */
  public function tagBased(array $node_data_array, string $tag_name) {
    foreach ($node_data_array['data'] as $node) {
      foreach ($node['tags'] as $tag) {
        if ($tag == $tag_name) {
          $result[] = $node;
        }
      }
    }
    return $result;
  }

}
