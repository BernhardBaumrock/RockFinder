# RockSqlFinder

Sample queries:
```php
$pages->findObjects(['template=person', ['id', 'title']]);
```
First parameter is ALWAYS an array of selector + fields.
Second parameter is optional (options).

Query with pagefield and the referenced title:
```php
$pages->findObjects(['template=person', ['id', 'title', 'cats', 'dogs']], [
  'joins' => [
    'catsjoin' => [['template=cat', ['id', 'title']], []],
    'dogsjoin' => [['template=dog', ['id', 'title']], []],
  ],
]);
```

Define custom names for your resulting properties:
```php
$pages->findObjects('template=cat', ['id', 'catname' => 'title']);
```


## Options

### Joins
```php
d($pages->findObjects(['template=person', ['id', 'status']], [
  'joins' => [
    'catsjoinsimple' => [['template=cat', ['id', 'status']], []],
    [['template=cat', ['id', 'status']], [
        'joins' => [
            [['template=basic-page', ['id']]],
        ],
    ]],
    'dogsjoin' => [['template=dog', ['id', 'created']], [
        'joins' => [
            'dogssubjoin' => [['template=basic-page', ['id']], [
                'joins' => [
                    [['template=basic-page', ['id']], []]
                ],
            ]],
        ],
    ]],
  ],
  //'sql' => true,
]), 'console', [6,999]);
```

### SQL
If you want to return the sql query instead of the array just set the "sql" option to true:
```php
d($pages->findObjects(['template=person', ['id', 'title', 'cats', 'dogs']], [
  'sql' => true,
]));
```












# notes

base query
```php
d($pages->findObjects(['template=person', ['id', 'status', 'title', 'cats']], [
    'joins' => [
        'cats' => [['title'], [
            // options
        ]]
    ]
]), 'console', [6,999]);
```

resulting join (working):
```sql
SELECT
  `root`.`id` AS `id`,
  `root`.`status` AS `status`,
  `root`.`title` AS `title`,
  `root.cats`.`id` AS `cats.id`,
  `root.cats`.`title` AS `cats.title`
FROM (
  SELECT `id`,`status`,`field_title`.`data` AS `title`,`field_cats`.`data` AS `cats` FROM `pages`
  LEFT JOIN `field_title` as `field_title` on `field_title`.`pages_id` = `pages`.`id`
  LEFT JOIN `field_cats` as `field_cats` on `field_cats`.`pages_id` = `pages`.`id`
) AS `root`
LEFT JOIN (
  SELECT
    `root.cats`.`id` AS `id`,
    `root.cats`.`title` AS `title`
  FROM (
    SELECT `id`,`field_title`.`data` AS `title` FROM `pages`
    LEFT JOIN `field_title` as `field_title` on `field_title`.`pages_id` = `pages`.`id`
) AS `root.cats`
  ) AS `root.cats` ON `root.cats`.`id` = `root`.`cats`
WHERE
  `root`.`id` IN (42023)
ORDER BY
  FIELD (`root`.`id`, 42023)
```

concat (todo):
```sql
SELECT
  `root`.`id` AS `id`,
  `root`.`status` AS `status`,
  `root`.`title` AS `title`,
  group_concat(`root.cats`.`id` separator ';') AS `cats.id`,
  group_concat(`root.cats`.`title` separator ';') AS `cats.title`
FROM (
  SELECT `id`,`status`,`field_title`.`data` AS `title`,`field_cats`.`data` AS `cats` FROM `pages`
  LEFT JOIN `field_title` as `field_title` on `field_title`.`pages_id` = `pages`.`id`
  LEFT JOIN `field_cats` as `field_cats` on `field_cats`.`pages_id` = `pages`.`id`
) AS `root`
LEFT JOIN (
  SELECT
    `root.cats`.`id` AS `id`,
    `root.cats`.`title` AS `title`
  FROM (
    SELECT `id`,`field_title`.`data` AS `title` FROM `pages`
    LEFT JOIN `field_title` as `field_title` on `field_title`.`pages_id` = `pages`.`id`
) AS `root.cats`
  ) AS `root.cats` ON `root.cats`.`id` = `root`.`cats`
WHERE
  `root`.`id` IN (42023)
ORDER BY
  FIELD (`root`.`id`, 42023)
```

sum (todo):
```sql
SELECT
  `root`.`id` AS `id`,
  `root`.`status` AS `status`,
  `root`.`title` AS `title`,
  sum(`root.cats`.`id`) AS `cats.id`,
  group_concat(`root.cats`.`title` separator ';') AS `cats.title`
FROM (
  SELECT `id`,`status`,`field_title`.`data` AS `title`,`field_cats`.`data` AS `cats` FROM `pages`
  LEFT JOIN `field_title` as `field_title` on `field_title`.`pages_id` = `pages`.`id`
  LEFT JOIN `field_cats` as `field_cats` on `field_cats`.`pages_id` = `pages`.`id`
) AS `root`
LEFT JOIN (
  SELECT
    `root.cats`.`id` AS `id`,
    `root.cats`.`title` AS `title`
  FROM (
    SELECT `id`,`field_title`.`data` AS `title` FROM `pages`
    LEFT JOIN `field_title` as `field_title` on `field_title`.`pages_id` = `pages`.`id`
) AS `root.cats`
  ) AS `root.cats` ON `root.cats`.`id` = `root`.`cats`
WHERE
  `root`.`id` IN (42023)
ORDER BY
  FIELD (`root`.`id`, 42023)
```




















---
---
---


## Usage

Basic usage is as simple as that:

```php
$pages->findObjects('id>0, limit=2');
```
This will result in a ```SELECT * FROM ...``` query and show all the columns of the queried table.

Better and more useful is to define properties of the returned objects like this:
```php
$pages->findObjects('id>0, limit=2', [
    'title', // title = "Home"
]);
```

The resulting query looks like this:
```sql
SELECT
  pages.`id` AS `id`,
  (SELECT `data` FROM `field_title` WHERE pages_id = pages.id) AS `title`
FROM pages
WHERE
  pages.id IN (1,3)
ORDER BY
  FIELD (pages.id,1,3)
```
The find will ALWAYS return the id of the found page as first parameter.

---

## Regular fields
## DOT fields
## Closure fields
## SQL fields



---
---
## TODO
```
'sum' => 'SELECT sum(data) FROM field_revenue WHERE ...',
```
























---
---
---


## Property definitions

The query can either be
1. a string
1. a key/value pair

### Strings

A simple string will return the content of this field:
```php
'title'

// result
title = "Home"
```

Using dot-notation you can query referenced data, eg the page title of a referenced page:
```php
'title',
'mypagefield.title',
'images.description',

// result
title = "Home"
mypagefield.title = "I am a referenced page"
images.description = "Image 1|Image 2|Image3"
```

Using SQL statements is also possible.
```php
'sqldemo' => 'SELECT #data# FROM field_title WHERE pages_id = pages.id',

// result
sqldemo = 'My page'
```
Using 100% custom SQL is also possible:
```php
'(SELECT #data# FROM field_title WHERE pages_id = pages.id) AS `sqldemo`,(SELECT #data# FROM field_myfield WHERE pages_id = pages.id) AS `sqldemo2`',

// result
sqldemo = 'My page'
sqldemo2 = 'MyField demo'
```

### Key/Value pairs

If you define key/value pairs, the key will be used as property name. The value can either be a string:

```php
'mycustomname' => 'title'

// result
mycustomname = 'Page title'
```

Or a closure:
```php
'mypagefield' => function($page) { return $page->path; },

// result
mypagefield = '/my/custom/page'
```
**Caution:** Using closures is a huge performance killer! Use this only when you are not dealing with many pages! (It's just as "slow" as a regular $pages->find() operation or even slower)


---

## Fields with multiple items

Fields with multiple items will return a concatenated string of the requested values. Some examples:

```php
'mypagefield'

// result
1010|1011|1012
```
```php
'mypagefield.title.status'

// result
mypagefield = 1010,1011,1012
mypagefield.title = Page1|Page2|Page3
mypagefield.status = 1|1|1
```
```php
'myimagefield'

// result
myimagefield = img1.jpg|img2.jpg|img3.jpg
```
```php
'myimagefield.description'

// result
myimagefield = img1.jpg|img2.jpg|img3.jpg
image1|image2|image3
```

---

## Language values

