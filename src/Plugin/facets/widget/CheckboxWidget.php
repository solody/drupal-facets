<?php

namespace Drupal\facets\Plugin\facets\widget;

use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;

/**
 * The checkbox / radios widget.
 *
 * @FacetsWidget(
 *   id = "checkbox",
 *   label = @Translation("List of checkboxes"),
 *   description = @Translation("A configurable widget that shows a list of checkboxes"),
 * )
 */
class CheckboxWidget extends LinksWidget {

  /**
   * The facet the widget is being built for.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet;

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $this->facet = $facet;

    /** @var \Drupal\facets\Result\Result[] $results */
    $results = $facet->getResults();
    $items = [];

    $configuration = $facet->getWidgetConfigs();
    $this->showNumbers = empty($configuration['show_numbers']) ? FALSE : (bool) $configuration['show_numbers'];

    foreach ($results as $result) {
      if (is_null($result->getUrl())) {
        $text = $this->extractText($result);
        $items[] = ['#markup' => $text];
      }
      else {
        $items[] = $this->buildListItems($result);
      }
    }

    $build = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['js-facets-checkbox-links']],
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
    ];
    $build['#attached']['library'][] = 'facets/drupal.facets.checkbox-widget';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildListItems(ResultInterface $result) {
    $items = parent::buildListItems($result);
    $items['#attributes']['data-facet-id'] = $this->facet->getUrlAlias() . '-' . $result->getRawValue();
    return $items;
  }

}
