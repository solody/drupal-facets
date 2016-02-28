<?php

/**
 * @file
 * Contains \Drupal\facets\FacetSource\SearchApiFacetSourceInterface.
 */

namespace Drupal\facets\FacetSource;

/**
 * A facet source that uses search api as a base.
 */
interface SearchApiFacetSourceInterface {

  /**
   * Returns the search_api index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search api index.
   */
  public function getIndex();

}
