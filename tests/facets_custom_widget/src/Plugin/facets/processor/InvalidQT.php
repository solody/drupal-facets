<?php

namespace Drupal\facets_custom_widget\Plugin\facets\processor;

use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * The URL processor handler triggers the actual url processor.
 *
 * @FacetsProcessor(
 *   id = "invalid_qt",
 *   label = @Translation("Invalid Query type"),
 *   description = @Translation("TEST invalid query type"),
 *   stages = {
 *     "pre_query" = 50
 *   }
 * )
 */
class InvalidQT extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return '51_pegasi_b';
  }

}
