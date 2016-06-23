<?php

namespace Drupal\facets\Widget;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;
use Drupal\facets\Result\ResultInterface;

/**
 * A base class for widgets that implements most of the boilerplate.
 */
abstract class WidgetPluginBase extends PluginBase implements WidgetPluginInterface {

  /**
   * Show the amount of results next to the result.
   *
   * @var bool
   */
  protected $showNumbers;

  /**
   * The facet the widget is being built for.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet;

  /**
   * Constructs a plugin object.
   *
   * @param array $configuration
   *   (optional) An optional configuration to be passed to the plugin. If
   *   empty, the plugin is initialized with its default plugin configuration.
   */
  public function __construct(array $configuration = []) {
    $plugin_id = $this->getPluginId();
    $plugin_definition = $this->getPluginDefinition();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $this->facet = $facet;

    $items = array_map(function (Result $result) {
      if (empty($result->getUrl())) {
        return ['#markup' => $this->extractText($result)];
      }
      else {
        return $this->buildListItems($result);
      }
    }, $facet->getResults());

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['data-drupal-facet-id' => $facet->id()],
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['show_numbers' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType($query_types) {
    return $query_types['string'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form['show_numbers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the amount of results'),
      '#default_value' => $this->getConfiguration()['show_numbers'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
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
    $items['#attributes']['data-drupal-facet-item-id'] = $this->facet->getUrlAlias() . '-' . $result->getRawValue();

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
      $link = (new Link($text, $result->getUrl()))->toRenderable();
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
   * Extracts the text for a result to display in the UI.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result to extract the text for.
   *
   * @return string
   *   The text to display.
   */
  protected function extractText(ResultInterface $result) {
    $template = '@text';
    $arguments = ['@text' => $result->getDisplayValue()];
    if ($this->getConfiguration()['show_numbers'] && $result->getCount() !== FALSE) {
      $template .= ' <span class="facet-count">(@count)</span>';
      $arguments += ['@count' => $result->getCount()];
    }
    if ($result->isActive()) {
      $template = '<span class="facet-deactivate">(-)</span> ' . $template;
    }
    return new FormattableMarkup($template, $arguments);
  }

}
