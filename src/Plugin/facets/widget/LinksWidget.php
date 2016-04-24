<?php

namespace Drupal\facets\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets\Widget\WidgetInterface;

/**
 * The links widget.
 *
 * @FacetsWidget(
 *   id = "links",
 *   label = @Translation("List of links"),
 *   description = @Translation("A simple widget that shows a list of links"),
 * )
 */
class LinksWidget implements WidgetInterface {

  use StringTranslationTrait;

  /**
   * A flag that indicates if we should display the numbers.
   *
   * @var bool
   */
  protected $showNumbers = FALSE;

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
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
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
    ];
    return $build;
  }

  /**
   * Builds a renderable array of result items.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   A result item.
   *
   * @return array
   *   A renderable array of the result.
   */
  protected function buildListItems(ResultInterface $result) {

    $classes = ['facet-item'];

    if ($children = $result->getChildren()) {
      $items = $this->prepareLink($result);

      $children_markup = [];
      foreach ($children as $child) {
        $children_markup[] = $this->buildChildren($child);
      }

      $classes[] = 'expanded';
      $items['children'] = [$children_markup];

      if ($result->isActive()) {
        $items['#attributes'] = ['class' => 'active-trail'];
      }
    }
    else {
      $items = $this->prepareLink($result);

      if ($result->isActive()) {
        $items['#attributes'] = ['class' => 'is-active'];
      }
    }

    $items['#wrapper_attributes'] = ['class' => $classes];

    return $items;
  }

  /**
   * Returns the text or link for an item.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   A result item.
   *
   * @return array
   *   The item, as a renderable array.
   */
  protected function prepareLink(ResultInterface $result) {
    $text = $this->extractText($result);

    if (is_null($result->getUrl())) {
      $link = ['#markup' => $text];
    }
    else {
      $link = new Link($text, $result->getUrl());
      $link = $link->toRenderable();
    }

    return $link;
  }

  /**
   * Builds a renderable array of a result.
   *
   * @param \Drupal\facets\Result\ResultInterface $child
   *   A result item.
   *
   * @return array
   *   A renderable array of the result.
   */
  protected function buildChildren(ResultInterface $child) {
    $text = $this->extractText($child);

    if (!is_null($child->getUrl())) {
      $link = new Link($text, $child->getUrl());
      $item = $link->toRenderable();
    }
    else {
      $item = ['#markup' => $text];
    }

    $item['#wrapper_attributes'] = ['class' => ['leaf']];

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $config) {

    $form['show_numbers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the amount of results'),
    ];

    if (!is_null($config)) {
      $widget_configs = $config->get('widget_configs');
      if (isset($widget_configs['show_numbers'])) {
        $form['show_numbers']['#default_value'] = $widget_configs['show_numbers'];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType($query_types) {
    return $query_types['string'];
  }

  /**
   * Extracts the text for a result to display in the UI.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result to extract the text for.
   *
   * @return string
   *   The text to display.
   */
  protected function extractText(ResultInterface $result) {
    $text = $result->getDisplayValue();
    if ($this->showNumbers && $result->getCount()) {
      $text .= ' (' . $result->getCount() . ')';
    }
    if ($result->isActive()) {
      $text = '(-) ' . $text;
    }
    return $text;
  }

}
