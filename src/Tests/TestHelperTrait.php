<?php

namespace Drupal\facets\Tests;

trait TestHelperTrait {

  /**
   * {@inheritdoc}
   */
  protected function assertFacetLabel($label, $index = 0, $message = '', $group = 'Other') {
    $label = (string) $label;
    $label = strip_tags($label);
    $matches = [];

    if (preg_match('/(.*)\s\((\d+)\)/', $label, $matches)) {
      $links = $this->xpath('//a//span[normalize-space(text())=:label]/following-sibling::span[normalize-space(text())=:count]', [':label' => $matches[1], ':count' => '(' . $matches[2] . ')']);
    }
    else {
      $links = $this->xpath('//a//span[normalize-space(text())=:label]', [':label' => $label]);
    }
    $message = ($message ? $message : strtr('Link with label %label found.', ['%label' => $label]));
    return $this->assert(isset($links[$index]), $message, $group);
  }

  /**
   * Check if a facet is active by providing a label for it.
   *
   * We'll check by activeness by seeing that there's a span with (-) in the
   * same link as the label.
   *
   * @param string $label
   *   The label of a facet that should be active.
   *
   * @return bool
   *   Returns true when the facet is found and is active.
   */
  protected function checkFacetIsActive($label) {
    $label = (string) $label;
    $label = strip_tags($label);
    $links = $this->xpath('//a/span[normalize-space(text())="(-)"]/following-sibling::span[normalize-space(text())=:label]', array(':label' => $label));
    return $this->assert(isset($links[0]));
  }

}
