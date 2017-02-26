<?php

namespace Drupal\Tests\facets_summary\Functional;

use Drupal\Tests\facets\Functional\FacetsTestBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the overall functionality of the Facets summary admin UI.
 *
 * @group facets
 */
class IntegrationTest extends FacetsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'facets_summary',
  ];

  /**
   * No config checking.
   *
   * @var bool
   *
   * @todo Enable config checking again.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalLogin($this->adminUser);

    $this->setUpExampleStructure();
    $this->insertExampleContent();
    $this->assertEqual($this->indexItems($this->indexId), 5, '5 items were indexed.');

    // Make absolutely sure the ::$blocks variable doesn't pass information
    // along between tests.
    $this->blocks = NULL;
  }

  /**
   * Tests the overall functionality of the Facets summary admin UI.
   */
  public function testFramework() {
    $this->drupalGet('admin/config/search/facets');
    $this->assertNoText('Facets Summary');

    $values = [
      'name' => 'Owl',
      'id' => 'owl',
      'facet_source_id' => 'search_api:views_page__search_api_test_view__page_1',
    ];
    $this->drupalPostForm('admin/config/search/facets/add-facet-summary', $values, 'Save');
    $this->drupalPostForm(NULL, [], 'Save');

    $this->drupalGet('admin/config/search/facets');
    $this->assertText('Facets Summary');
    $this->assertText('Owl');

    $this->drupalGet('admin/config/search/facets/facet-summary/owl/edit');
    $this->assertText('No facets found.');

    $this->createFacet('Llama', 'llama');
    $this->drupalGet('admin/config/search/facets');
    $this->assertText('Llama');

    // Go back to the facet summary and check that the facets are not checked by
    // default and that they show up in the list here.
    $this->drupalGet('admin/config/search/facets/facet-summary/owl/edit');
    $this->assertNoText('No facets found.');
    $this->assertText('Llama');
    $this->assertNoFieldChecked('edit-facets-llama-checked');

    // Post the form and check that no facets are checked after saving the form.
    $this->drupalPostForm(NULL, [], 'Save');
    $this->assertNoFieldChecked('edit-facets-llama-checked');

    // Enable a facet and check it's status after saving.
    $this->drupalPostForm(NULL, ['facets[llama][checked]' => TRUE], 'Save');
    $this->assertFieldChecked('edit-facets-llama-checked');
  }

  /**
   * Tests with multiple facets.
   *
   * Includes a regression test for #2841357
   */
  public function testMultipleFacets() {
    // Create facets.
    $this->createFacet('Giraffe', 'giraffe', 'keywords');
    // Clear all the caches between building the 2 facets - because things fail
    // otherwise.
    $this->resetAll();
    $this->createFacet('Llama', 'llama');

    // Add a summary.
    $values = [
      'name' => 'Owlß',
      'id' => 'owl',
      'facet_source_id' => 'search_api:views_page__search_api_test_view__page_1',
    ];
    $this->drupalPostForm('admin/config/search/facets/add-facet-summary', $values, 'Save');
    $this->drupalPostForm(NULL, [], 'Save');

    // Edit the summary and enable the giraffe's.
    $summaries = [
      'facets[giraffe][checked]' => TRUE,
      'facets[giraffe][label]' => 'Summary giraffe',
    ];
    $this->drupalPostForm('admin/config/search/facets/facet-summary/owl/edit', $summaries, 'Save');

    $block = [
      'region' => 'footer',
      'id' => str_replace('_', '-', 'owl'),
      'weight' => 50,
    ];
    $block = $this->drupalPlaceBlock('facets_summary_block:owl', $block);

    $this->drupalGet('search-api-test-fulltext');
    $this->assertText('Displaying 5 search results');
    $this->assertText($block->label());
    $this->assertFacetBlocksAppear();

    $this->clickLink('apple');
    $list_items = $this->getSession()
      ->getPage()
      ->findById('block-' . $block->id())
      ->findAll('css', 'li');
    $this->assertCount(1, $list_items);

    $this->clickLink('item');
    $list_items = $this->getSession()
      ->getPage()
      ->findById('block-' . $block->id())
      ->findAll('css', 'li');
    $this->assertCount(1, $list_items);

    // Edit the summary and enable the giraffe's.
    $summaries = [
      'facets[giraffe][checked]' => TRUE,
      'facets[giraffe][label]' => 'Summary giraffe',
      'facets[llama][checked]' => TRUE,
      'facets[llama][label]' => 'Summary llama',
    ];
    $this->drupalPostForm('admin/config/search/facets/facet-summary/owl/edit', $summaries, 'Save');

    $this->drupalGet('search-api-test-fulltext');
    $this->assertText('Displaying 5 search results');
    $this->assertText($block->label());
    $this->assertFacetBlocksAppear();

    $this->clickLink('apple');
    $list_items = $this->getSession()
      ->getPage()
      ->findById('block-' . $block->id())
      ->findAll('css', 'li');
    $this->assertCount(1, $list_items);

    $this->clickLink('item');
    $list_items = $this->getSession()
      ->getPage()
      ->findById('block-' . $block->id())
      ->findAll('css', 'li');
    $this->assertCount(2, $list_items);
  }
}
