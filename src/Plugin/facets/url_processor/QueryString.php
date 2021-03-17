<?php

namespace Drupal\facets\Plugin\facets\url_processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Url;
use Drupal\facets\Event\QueryStringCreated;
use Drupal\facets\FacetInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Query string URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "query_string",
 *   label = @Translation("Query string"),
 *   description = @Translation("Query string is the default Facets URL processor, and uses GET parameters, for example ?f[0]=brand:drupal&f[1]=color:blue")
 * )
 */
class QueryString extends UrlProcessorPluginBase {

  /**
   * A string of how to represent the facet in the url.
   *
   * @var string
   */
  protected $urlAlias;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $eventDispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $entity_type_manager);
    $this->eventDispatcher = $eventDispatcher;
    $this->initializeActiveFilters();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getMasterRequest(),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrls(FacetInterface $facet, array $results) {
    // No results are found for this facet, so don't try to create urls.
    if (empty($results)) {
      return [];
    }

    // First get the current list of get parameters.
    $get_params = $this->request->query;

    // When adding/removing a filter the number of pages may have changed,
    // possibly resulting in an invalid page parameter.
    if ($get_params->has('page')) {
      $current_page = $get_params->get('page');
      $get_params->remove('page');
    }

    // Set the url alias from the facet object.
    $this->urlAlias = $facet->getUrlAlias();

    $facet_source_path = $facet->getFacetSource()->getPath();
    $request = $this->getRequestByFacetSourcePath($facet_source_path);
    $requestUrl = $this->getUrlForRequest($facet_source_path, $request);

    $original_filter_params = [];
    foreach ($this->getActiveFilters() as $facet_id => $values) {
      $values = array_filter($values, static function ($it) {
        return $it !== NULL;
      });
      foreach ($values as $value) {
        $original_filter_params[] = $this->getUrlAliasByFacetId($facet_id, $facet->getFacetSourceId()) . ":" . $value;
      }
    }

    /** @var \Drupal\facets\Result\ResultInterface[] $results */
    foreach ($results as &$result) {
      // Reset the URL for each result.
      $url = clone $requestUrl;

      // Sets the url for children.
      if ($children = $result->getChildren()) {
        $this->buildUrls($facet, $children);
      }

      if ($result->getRawValue() === NULL) {
        $filter_string = NULL;
      }
      else {
        $filter_string = $this->urlAlias . $this->getSeparator() . $result->getRawValue();
      }
      $result_get_params = clone $get_params;

      $filter_params = $original_filter_params;

      // If the value is active, remove the filter string from the parameters.
      if ($result->isActive()) {
        foreach ($filter_params as $key => $filter_param) {
          if ($filter_param == $filter_string) {
            unset($filter_params[$key]);
          }
        }
        if ($facet->getEnableParentWhenChildGetsDisabled() && $facet->getUseHierarchy()) {
          // Enable parent id again if exists.
          $parent_ids = $facet->getHierarchyInstance()->getParentIds($result->getRawValue());
          if (isset($parent_ids[0]) && $parent_ids[0]) {
            // Get the parents children.
            $child_ids = $facet->getHierarchyInstance()->getNestedChildIds($parent_ids[0]);

            // Check if there are active siblings.
            $active_sibling = FALSE;
            if ($child_ids) {
              foreach ($results as $result2) {
                if ($result2->isActive() && $result2->getRawValue() != $result->getRawValue() && in_array($result2->getRawValue(), $child_ids)) {
                  $active_sibling = TRUE;
                  continue;
                }
              }
            }
            if (!$active_sibling) {
              $filter_params[] = $this->urlAlias . $this->getSeparator() . $parent_ids[0];
            }
          }
        }

      }
      // If the value is not active, add the filter string.
      else {
        if ($filter_string !== NULL) {
          $filter_params[] = $filter_string;
        }

        if ($facet->getUseHierarchy()) {
          // If hierarchy is active, unset parent trail and every child when
          // building the enable-link to ensure those are not enabled anymore.
          $parent_ids = $facet->getHierarchyInstance()->getParentIds($result->getRawValue());
          $child_ids = $facet->getHierarchyInstance()->getNestedChildIds($result->getRawValue());
          $parents_and_child_ids = array_merge($parent_ids, $child_ids);
          foreach ($parents_and_child_ids as $id) {
            $filter_params = array_diff($filter_params, [$this->urlAlias . $this->getSeparator() . $id]);
          }
        }
        // Exclude currently active results from the filter params if we are in
        // the show_only_one_result mode.
        if ($facet->getShowOnlyOneResult()) {
          foreach ($results as $result2) {
            if ($result2->isActive()) {
              $active_filter_string = $this->urlAlias . $this->getSeparator() . $result2->getRawValue();
              foreach ($filter_params as $key2 => $filter_param2) {
                if ($filter_param2 == $active_filter_string) {
                  unset($filter_params[$key2]);
                }
              }
            }
          }
        }
      }

      // Allow other modules to alter the result url built.
      $event = new QueryStringCreated($result_get_params, $filter_params, $result, $this->activeFilters, $facet);
      $this->eventDispatcher->dispatch(QueryStringCreated::NAME, $event);
      $filter_params = $event->getFilterParameters();

      asort($filter_params, \SORT_NATURAL);
      $result_get_params->set($this->filterKey, array_values($filter_params));
      if (!empty($routeParameters)) {
        $url->setRouteParameters($routeParameters);
      }

      if ($result_get_params->all() !== [$this->filterKey => []]) {
        $new_url_params = $result_get_params->all();

        // Facet links should be page-less.
        // See https://www.drupal.org/node/2898189.
        unset($new_url_params['page']);

        // Remove core wrapper format (e.g. render-as-ajax-response) paremeters.
        unset($new_url_params[MainContentViewSubscriber::WRAPPER_FORMAT]);

        // Set the new url parameters.
        $url->setOption('query', $new_url_params);
      }

      $result->setUrl($url);
    }

    // Restore page parameter again. See https://www.drupal.org/node/2726455.
    if (isset($current_page)) {
      $get_params->set('page', $current_page);
    }
    return $results;
  }

  /**
   * Gets a request object based on the facet source path.
   *
   * If the facet's source has a path, we construct a request object based on
   * that path, as it may be different than the current request's. This method
   * statically caches the request object based on the facet source path so that
   * subsequent calls to this processer do not recreate the same request object.
   *
   * @param string $facet_source_path
   *   The facet source path.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function getRequestByFacetSourcePath($facet_source_path) {
    $requestsByPath = &drupal_static(__CLASS__ . __FUNCTION__, []);
    if (!$facet_source_path) {
      return $this->request;
    }

    if (array_key_exists($facet_source_path, $requestsByPath)) {
      return $requestsByPath[$facet_source_path];
    }

    $request = Request::create($facet_source_path);
    $request->attributes->set('_format', $this->request->get('_format'));
    $requestsByPath[$facet_source_path] = $request;
    return $request;
  }

  /**
   * Gets the URL object for a request.
   *
   * This method statically caches the URL object for a request based on the
   * facet source path. This reduces subsequent calls to the processor from
   * having to regenerate the URL object.
   *
   * @param string $facet_source_path
   *   The facet source path.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  protected function getUrlForRequest($facet_source_path, Request $request) {
    /** @var \Drupal\Core\Url[] $requestUrlsByPath */
    $requestUrlsByPath = &drupal_static(__CLASS__ . __FUNCTION__, []);

    if (array_key_exists($facet_source_path, $requestUrlsByPath)) {
      return $requestUrlsByPath[$facet_source_path];
    }

    // Try to grab any route params from the original request.
    // In case of request path not having a matching route, Url generator will
    // fail with.
    try {
      $requestUrl = Url::createFromRequest($request);
    }
    catch (ResourceNotFoundException $e) {
      // Bypass exception if no path available.
      // Should be unreachable in default FacetSource implementations,
      // but you never know.
      if (!$facet_source_path) {
        throw $e;
      }

      $requestUrl = Url::fromUserInput($facet_source_path, [
        'query' => [
          '_format' => $this->request->get('_format'),
        ],
      ]);
    }

    $requestUrl->setOption('attributes', ['rel' => 'nofollow']);
    $requestUrlsByPath[$facet_source_path] = $requestUrl;
    return $requestUrl;
  }

  /**
   * Initializes the active filters from the request query.
   *
   * Get all the filters that are active by checking the request query and store
   * them in activeFilters which is an array where key is the facet id and value
   * is an array of raw values.
   */
  protected function initializeActiveFilters() {
    $url_parameters = $this->request->query;

    // Get the active facet parameters.
    $active_params = $url_parameters->get($this->filterKey, [], TRUE);
    $facet_source_id = $this->configuration['facet']->getFacetSourceId();

    // When an invalid parameter is passed in the url, we can't do anything.
    if (!is_array($active_params)) {
      return;
    }

    // Explode the active params on the separator.
    foreach ($active_params as $param) {
      $explosion = explode($this->getSeparator(), $param);
      $url_alias = array_shift($explosion);
      $facet_id = $this->getFacetIdByUrlAlias($url_alias, $facet_source_id);
      $value = '';
      while (count($explosion) > 0) {
        $value .= array_shift($explosion);
        if (count($explosion) > 0) {
          $value .= $this->getSeparator();
        }
      }
      if (!isset($this->activeFilters[$facet_id])) {
        $this->activeFilters[$facet_id] = [$value];
      }
      else {
        $this->activeFilters[$facet_id][] = $value;
      }
    }
  }

  /**
   * Gets the facet id from the url alias & facet source id.
   *
   * @param string $url_alias
   *   The url alias.
   * @param string $facet_source_id
   *   The facet source id.
   *
   * @return bool|string
   *   Either the facet id, or FALSE if that can't be loaded.
   */
  protected function getFacetIdByUrlAlias($url_alias, $facet_source_id) {
    $mapping = &drupal_static(__FUNCTION__);
    if (!isset($mapping[$facet_source_id][$url_alias])) {
      $storage = $this->entityTypeManager->getStorage('facets_facet');
      $facet = current($storage->loadByProperties(['url_alias' => $url_alias, 'facet_source_id' => $facet_source_id]));
      if (!$facet) {
        return NULL;
      }
      $mapping[$facet_source_id][$url_alias] = $facet->id();
    }
    return $mapping[$facet_source_id][$url_alias];
  }

  /**
   * Gets the url alias from the facet id & facet source id.
   *
   * @param string $facet_id
   *   The facet id.
   * @param string $facet_source_id
   *   The facet source id.
   *
   * @return bool|string
   *   Either the url alias, or FALSE if that can't be loaded.
   */
  protected function getUrlAliasByFacetId($facet_id, $facet_source_id) {
    $mapping = &drupal_static(__FUNCTION__);
    if (!isset($mapping[$facet_source_id][$facet_id])) {
      $storage = $this->entityTypeManager->getStorage('facets_facet');
      $facet = current($storage->loadByProperties(['id' => $facet_id, 'facet_source_id' => $facet_source_id]));
      if (!$facet) {
        return FALSE;
      }
      $mapping[$facet_source_id][$facet_id] = $facet->getUrlAlias();
    }
    return $mapping[$facet_source_id][$facet_id];
  }

}
