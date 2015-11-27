<?php

/**
 * @file
 * Contains \Drupal\Tests\facetapi\Plugin\Processor\UidToUserNameCallbackProcessorTest.
 */

namespace Drupal\Tests\facetapi\Unit\Plugin\Processor;

use Drupal\facetapi\Entity\Facet;
use Drupal\facetapi\Plugin\facetapi\processor\UidToUserNameCallbackProcessor;
use Drupal\facetapi\Result\Result;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @group facetapi
 */
class UidToUserNameCallbackProcessorTest extends UnitTestCase {

  /**
   * The processor to be tested.
   *
   * @var \Drupal\facetapi\processor\WidgetOrderProcessorInterface
   */
  protected $processor;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();

    $this->processor = new UidToUserNameCallbackProcessor([], 'uid_to_username_callback', []);

    $this->createMocks();
  }


  /**
   * Test that results were correctly changed
   */
  public function testResultsChanged() {
    $original_results = [
      new Result(1, 1, 5),
    ];

    $facet = new Facet([], 'facet');
    $facet->setResults($original_results);

    $expected_results = [
      ['uid' => 1, 'name' => 'Admin'],
    ];

    foreach ($expected_results as $key => $expected) {
      $this->assertEquals($expected['uid'], $original_results[$key]->getRawValue());
      $this->assertEquals($expected['uid'], $original_results[$key]->getDisplayValue());
    }

    $filtered_results = $this->processor->build($facet, $original_results);

    foreach ($expected_results as $key => $expected) {
      $this->assertEquals($expected['uid'], $filtered_results[$key]->getRawValue());
      $this->assertEquals($expected['name'], $filtered_results[$key]->getDisplayValue());
    }
  }

  /**
   * Creates and sets up the container to be used in tests.
   */
  protected function createMocks() {
    $userStorage = $this->getMock('\Drupal\Core\Entity\EntityStorageInterface');
    $entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');
    $entityManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($userStorage);

    $user1 = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $user1->method('getDisplayName')
      ->willReturn('Admin');

    $userStorage->method('load')
      ->willReturn($user1);

    $container = new ContainerBuilder();
    $container->set('entity.manager', $entityManager);
    \Drupal::setContainer($container);
  }

}