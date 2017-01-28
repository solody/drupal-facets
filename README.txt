CONTENTS OF THIS FILE
---------------------
 * Requirements
 * Installation
 * Configuration
 * FAQ

REQUIREMENTS
------------
No other modules required, we're supporting drupal core as a source for creating
facets. Though we recommend using Search API, as that integration is better
tested.

INSTALLATION
------------
 * Install as you would normally install a contributed drupal module. See:
  https://www.drupal.org/docs/8/extending-drupal-8/installing-contributed-modules-find-import-enable-configure-drupal-8
  for further information.

CONFIGURATION
-------------
Before adding a facet, there should be a facet source. Facet sources can be:
- Drupal core's search.
- A view based on a Search API index with a page display.
- A page from the search_api_page module.

After adding one of those, you can add a facet on the facets configuration page:
/admin/config/search/facets

If you're using Search API views, make sure to disable views cache when using
facets for that view.

FAQ
---

Q: Why do the facets disappear after a refresh.
A: We don't support cached views, change the view to disable caching.

FEATURES
--------

If you are the developer of a search api backend implementation and want
to support facets with your service class, too, you'll have to support the
"search_api_facets" feature. In short, you'll just have to return facet terms
and counts according to the query's "search_api_facets" option, when executing a
query.
In order for the module to be able to tell that your server supports facets,
you will also have to change your service's supportsFeature() method to
something like the following:

```
  public function getSupportedFeatures() {
    return ['search_api_facets'];
  }
```

If you don't do that, there's no way for the facet source to pick up facets.

The "search_api_facets" option looks as follows:

```
$query->setOption('search_api_facets', [
  $facet_id => [
    // The Search API field ID of the field to facet on.
    'field' => (string),
    // The maximum number of filters to retrieve for the facet.
    'limit' => (int),
    // The facet operator: "and" or "or".
    'operator' => (string),
    // The minimum count a filter/value must have to be returned.
    'min_count' => (int),
    // Whether to retrieve a facet for "missing" values.
    'missing' => (bool),
  ],
  // …
]);
```

The structure of the returned facets array should look like this:

```
$results->setExtraData('search_api_facets', [
  $facet_id => [
    [
      'count' => (int),
      'filter' => (string),
    ],
    // …
  ],
  // …
]);
```

A filter is a string with one of the following forms:
- `"VALUE"`: Filter by the literal value VALUE (always include the quotes, not
  only for strings).
- `[VALUE1 VALUE2]`: Filter for a value between VALUE1 and VALUE2. Use
  parantheses for excluding the border values and square brackets for including
  them. An asterisk (*) can be used as a wildcard. E.g., (* 0) or [* 0) would be
  a filter for all negative values.
- `!`: Filter for items without a value for this field (i.e., the "missing"
  facet).
