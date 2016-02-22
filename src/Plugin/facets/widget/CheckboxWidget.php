<?php

/**
 * @file
 * Contains \Drupal\facets\Plugin\facets\widget\CheckboxWidget.
 */

namespace Drupal\facets\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facets\FacetInterface;
use Drupal\facets\Form\CheckboxWidgetForm;
use Drupal\facets\Widget\WidgetInterface;

/**
 * The checkbox / radios widget.
 *
 * @FacetsWidget(
 *   id = "checkbox",
 *   label = @Translation("List of checkboxes"),
 *   description = @Translation("A configurable widget that shows a list of checkboxes"),
 * )
 */
class CheckboxWidget implements WidgetInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $form_builder = \Drupal::getContainer()->get('form_builder');

    $form_object = new CheckboxWidgetForm($facet);

    // The form builder's getForm method accepts 1 argument in the interface,
    // the form ID. Extra arguments get passed into the form states addBuildInfo
    // method. This way we can pass the facet to the
    // \Drupal\facets\Form\CheckboxWidgetForm::buildForm method, it uses
    // FormState::getBuildInfo to get the facet out.
    $build = $form_builder->getForm($form_object, $facet);

    return $build;
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

}
