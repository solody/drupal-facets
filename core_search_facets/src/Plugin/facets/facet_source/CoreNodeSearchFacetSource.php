<?php

/**
 * @file
 * Contains \Drupal\core_search_facets\Plugin\facets\facet_source\CoreNodeSearchFacetSource.
 */

namespace Drupal\core_search_facets\Plugin\facets\facet_source;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\core_search_facets\Plugin\CoreSearchFacetSourceInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\FacetSourcePluginBase;
use Drupal\facets\FacetSource\FacetSourcePluginInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search\SearchPageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Represents a facet source which represents the search api views.
 *
 * @FacetsFacetSource(
 *   id = "core_node_search",
 *   deriver = "Drupal\core_search_facets\Plugin\facets\facet_source\CoreNodeSearchFacetSourceDeriver"
 * )
 */
class CoreNodeSearchFacetSource extends FacetSourcePluginBase implements CoreSearchFacetSourceInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager|null
   */
  protected $entityTypeManager;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager|null
   */
  protected $typedDataManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   */
  protected $configFactory;

  protected $searchManager;

  /**
   * The facet query being executed.
   */
  protected $facetQueryExtender;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, $query_type_plugin_manager, $search_manager, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $query_type_plugin_manager);
    $this->searchManager = $search_manager;
    $this->setSearchKeys($request_stack->getMasterRequest()->query->get('keys'));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $container->get('request_stack');

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.facets.query_type'),
      $container->get('plugin.manager.search'),
      $request_stack
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getPath() {
    /*$view = Views::getView($this->pluginDefinition['view_id']);
    $view->setDisplay($this->pluginDefinition['view_display']);
    $view->execute();

    return $view->getDisplay()->getOption('path');*/
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function fillFacetsWithResults($facets) {
    foreach ($facets as $facet) {
      $configuration = array(
        'query' => NULL,
        'facet' => $facet,
      );

      // Get the Facet Specific Query Type so we can process the results
      // using the build() function of the query type.
      /** @var \Drupal\facets\Entity\Facet $facet **/
      $query_type = $this->queryTypePluginManager->createInstance($facet->getQueryType(), $configuration);
      $query_type->build();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryTypesForFacet(FacetInterface $facet) {
    // Verify if the field exists. Otherwise the type will be a column
    // (type,uid...) from the node and we can use the field identifier directly.
    if ($field = FieldStorageConfig::loadByName('node', $facet->getFieldIdentifier())) {
      $field_type = $field->getType();
    }
    else {
      $field_type = $facet->getFieldIdentifier();
    }

    return $this->getQueryTypesForFieldType($field_type);
  }

  /**
   * Get the query types for a field type.
   *
   * @param string $field_type
   *   The field type.
   *
   * @return array
   *   An array of query types.
   */
  public function getQueryTypesForFieldType($field_type) {
    $query_types = [];
    switch ($field_type) {
      case 'type':
      case 'uid':
      case 'langcode':
      case 'entity_reference':
        $query_types['string'] = 'core_node_search_string';
        break;
    }

    return $query_types;
  }

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest() {
    // @TODO Avoid the use of \Duupal so maybe inject?
    $request = \Drupal::requestStack()->getMasterRequest();
    $search_page = $request->attributes->get('entity');
    if ($search_page instanceof SearchPageInterface) {
      $facet_source_id = 'core_node_search:' . $search_page->id();
      if ($facet_source_id == $this->getPluginId()) {
        return TRUE;
      }
    }

    return FALSE;

  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet, FacetSourcePluginInterface $facet_source) {

    $form['field_identifier'] = [
      '#type' => 'select',
      '#options' => $this->getFields(),
      '#title' => $this->t('Facet field'),
      '#description' => $this->t('Choose the indexed field.'),
      '#required' => TRUE,
      '#default_value' => $facet->getFieldIdentifier(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    // Default fields.
    $facet_fields = $this->getDefaultFields();
    // @TODO Only taxonomy term reference for the moment.
    // Get the current field instances and detect if the field type is allowed.
    $fields = FieldConfig::loadMultiple();
    foreach ($fields as $field) {
      if ($field->getFieldStorageDefinition()->getSetting('target_type') == 'taxonomy_term') {
        /** @var \Drupal\field\Entity\FieldConfig $field */
        if (!array_key_exists($field->getName(), $facet_fields)) {
          $facet_fields[$field->getName()] = $this->t('@label', ['@label' => $field->getLabel()]);
        }
      }
    }

    return $facet_fields;
  }

  /**
   * Getter for default node fields.
   *
   * @return array
   */
  protected function getDefaultFields() {
    return [
      'type' => $this->t('Content Type'),
      'uid' => $this->t('Author'),
      'langcode' => $this->t('Language'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFacetQueryExtender() {
     if (!$this->facetQueryExtender) {
       $this->facetQueryExtender = db_select('search_index', 'i', array('target' => 'replica'))->extend('Drupal\core_search_facets\FacetsQuery');
       $this->facetQueryExtender->join('node_field_data', 'n', 'n.nid = i.sid');
       $this->facetQueryExtender
         // ->condition('n.status', 1).
         ->addTag('node_access')
         ->searchExpression($this->keys, 'node_search');
     }
    return $this->facetQueryExtender;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryInfo(FacetInterface $facet) {
    $query_info = [];
    $field_name = $facet->getFieldIdentifier();
    $default_fields = $this->getDefaultFields();
    if (array_key_exists($facet->getFieldIdentifier(), $default_fields)) {
      // We add the language code of the indexed item to the result of the query.
      // So in this case we need to use the search_index table alias (i) for the
      // langcode field. Otherwise we will have same nid for multiple languages
      // as result. For more details see NodeSearch::findResults().
      // @TODO review if I can refactor this.
      $table_alias = $facet->getFieldIdentifier() == 'langcode' ? 'i' : 'n';
      $query_info = [
        'fields' => [
          $table_alias . '.' . $facet->getFieldIdentifier() => [
            'table_alias' => $table_alias,
            'field' => $facet->getFieldIdentifier(),
          ],
        ],
      ];
    }
    else {
      // Gets field info, finds table name and field name.
      $table = "node__{$field_name}";
      $column = $facet->getFieldIdentifier() . '_target_id';
      $query_info['fields'][$field_name . '.' . $column] = array(
        'table_alias' => $table,
        'field' => $column,
      );

      // Adds the join on the node table.
      $query_info['joins'] = array(
        $table => array(
          'table' => $table,
          'alias' => $table,
          'condition' => "n.vid = $table.revision_id AND i.langcode = $table.langcode",
        ),
      );
    }

    // Returns query info, makes sure all keys are present.
    return $query_info + [
      'joins' => [],
      'fields' => [],
    ];
  }

  /**
   * Checks if the search has facets.
   *
   * @TODO move to the Base class???
   */
  public function hasFacets() {
    $manager = \Drupal::service('entity_type.manager')->getStorage('facets_facet');
    $facets = $manager->loadMultiple();
    foreach ($facets as $facet) {
      if ($facet->getFacetSourceId() == $this->getPluginId()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
