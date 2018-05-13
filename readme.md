# RockFinder

## WHY?

This module was built to fill the gap between simple $pages->find() operations and complex SQL queries.

The problem with $pages->find() is that it loads all pages into memory and that can be a problem when querying multiple thousands of pages. Even $pages->findMany() loads all pages into memory and therefore is a lot slower than regular SQL.

The problem with SQL on the other hand is, that the queries are quite complex to build. All fields are separate tables, some repeatable fields use multiple rows for their content that belong to only one single page, you always need to check for the page status (which is not necessary on regular find() operations and therefore nobody is used to that).

In short: It is far too much work to efficiently and easily get an array of data based on PW pages and fields and I need that a lot for my RockGrid module to build all kinds of tabular data.

---

# Basic Usage

## getObjects()

Returns an array of objects.

![screenshot](screenshots/getObjects.png?raw=true "Screenshot")

## getArrays()

Returns an array of arrays.

![screenshot](screenshots/getArrays.png?raw=true "Screenshot")

## getValues()

Returns a flat array of values of the given column/field.

![screenshot](screenshots/getValues.png?raw=true "Screenshot")

## getPages()

Returns PW Page objects.

```php
$finder = new RockFinder('template=invoice, limit=5', ['value', 'date']);
$finder->getPages();
```

By default uses the id column, but another one can be specified:

![screenshot](screenshots/getPages.png?raw=true "Screenshot")

---

# Advanced Usage

## Custom SQL: Aggregations, Groupings, Distincts...

You can apply any custom SQL with this technique:

```php
$finder = new RockFinder('template=invoice, limit=0', ['value', 'date']);
$sql = $finder->getSQL();
$finder->sql = "SELECT * FROM ($sql) AS tmp";
d($finder->getObjects());
```

Real example:

```php
$finder = new RockFinder('template=invoice, limit=0', ['value', 'date']);
$sql = $finder->getSQL();
$finder->sql = "SELECT id, SUM(value) AS revenue, DATE_FORMAT(date, '%Y-%m') AS dategroup FROM ($sql) AS tmp GROUP BY dategroup";
d($finder->getObjects());
```

![screenshot](screenshots/groupby.png?raw=true "Screenshot")

Notice that this query takes only 239ms and uses 0.19MB of memory while it queries and aggregates more than 10.000 pages!


## Closures

ATTENTION: This executes a $pages->find() operation on each row, so this makes the whole query significantly slower than without using closures. Closures are a good option if you need to query complex data and only have a very limited number of rows.

![screenshot](screenshots/closures.png?raw=true "Screenshot")

## Querying more complex fields (page reference fields, repeaters, etc)

Querying those fields is not an easy task in SQL because the field's data is spread across several database tables. This data then needs to be joined and you need to make sure that the sort order stays untouched. RockFinder takes care of all that and makes the final query very easy.

See this example of a page reference field called `cats`:

![screenshot](screenshots/pageField.png?raw=true "Screenshot")

The example also shows how you can control the returned content (for example changing the separator symbol). For every supported fieldtype there is a corresponding readme-file in the `fieldTypes` folder of this repo.

You can create custom fieldType-queries by placing a file in `/site/assets/RockFinder`. This makes this module very versatile and you should be able to handle even the most complex edge-case.

---

# Multilanguage

Multilanguage is ON by default. Options:
```php
$finder->strictLanguage = false;
$finder->multiLang = true;
```

tbd