<?php

/**
 * @file
 * Contains \Drupal\facets\Tests\FacetSourceTest.
 */

namespace Drupal\facets\Tests;

use \Drupal\facets\Tests\WebTestBase as FacetWebTestBase;

/**
 * Tests the functionality of the facet source config entity.
 *
 * @group facets
 */
class FacetSourceTest extends FacetWebTestBase {

  /**
   * Test the facet source editing.
   */
  public function testFacetSource() {
    // Make sure we're logged in with a user that has sufficient permissions.
    $this->drupalLogin($this->adminUser);

    // Test the overview.
    $this->drupalGet('admin/config/search/facets');
    $this->assertLink($this->t('Configure'));
    $this->clickLink($this->t('Configure'));

    // Test the edit page.
    $this->assertField('filterKey');
    $this->drupalPostForm(NULL, array('filterKey' => 'fq'), $this->t('Save'));

    // Test that saving worked.
    $this->assertField('filterKey');
    $this->assertRaw('fq');
  }

}
