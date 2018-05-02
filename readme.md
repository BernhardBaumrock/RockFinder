# RockFinder

## WHY?

This module was built to fill the gap between simple $pages->find() operations and complex SQL queries.

The problem with $pages->find() is that it loads all pages into memory and that can be a problem when querying multiple thousands of pages. Even $pages->findMany() loads all pages into memory and therefore is a lot slower than regular SQL.

The problem with SQL on the other hand is, that the queries are quite complex to build. All fields are separate tables, some repeatable fields use multiple rows for their content that belong to only one single page, you always need to check for the page status (which is not necessary on regular find() operations and therefore nobody is used to that).

In short: It is far too much work to efficiently and easily get an array of data based on PW pages and fields and I need that a lot for my RockGrid module to build all kinds of tabular data.

---

## Examples

### Add multiple fields
```php
$finder = new RockFinder('template=person');
foreach($finder->addFields(['status', 'created', 'templates_id']) as $f) {
    d($f->getSql());
    d($f->getObjects()[0]);
}
```
Sql
```sql
SELECT
  `status` AS `status`
FROM `pages` AS `status`

SELECT
  `created` AS `created`
FROM `pages` AS `created`

SELECT
  `templates_id` AS `templates_id`
FROM `pages` AS `templates_id`
```
Result
```php
stdClass #7b6e
status => "1"

stdClass #f948
created => "2018-04-12 15:28:55" (19)

stdClass #50e0
templates_id => "1"
```

---

### Closures

ATTENTION: This executes a $pages->find() operation on each row, so this makes the whole query significantly slower than without using closures. Closures are a good option if you need to query complex data and only have a very limited number of rows.

```php
$finder = new RockFinder("id>0", [
  'path' => function($page) {
    return $page->path;
  },
]);
```

---

### Multilanguage

Multilanguage is ON by default. Options:
```php
$finder->strictLanguage = false;
$finder->multiLang = true;
```

test