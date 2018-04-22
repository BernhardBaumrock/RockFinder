# Examples for this fieldtype

Simple example 
```php
$finder = new RockFinder('template=person');
$f = $finder->addField('images');
d($f->getObjects()[0]);
```
sql
```sql
SELECT
  `pages_id` AS `pageid`,
  GROUP_CONCAT(`data` ORDER BY `sort` SEPARATOR ',') AS `images`
FROM `field_images` AS `images`
GROUP BY `pageid`
```
result
```php
stdClass #3765
pageid => "1"
images => "airport_cartoon_3.jpg,rough_cartoon_puppet.jpg" (46)
```

---

Simple example with additional fields
```php
$finder = new RockFinder('template=person');
$f = $finder->addField('images', ['description', 'created']);
d($f->getObjects()[0]);
```
sql
```sql
SELECT
  `pages_id` AS `pageid`,
  GROUP_CONCAT(`data` ORDER BY `sort` SEPARATOR ',') AS `images`,
  GROUP_CONCAT(`description` ORDER BY `sort` SEPARATOR ',') AS `images.description`,
  GROUP_CONCAT(`created` ORDER BY `sort` SEPARATOR ',') AS `images.created`
FROM `field_images` AS `images`
GROUP BY `pageid`
```
result
```php
stdClass #ae75
pageid => "1"
images => "airport_cartoon_3.jpg,rough_cartoon_puppet.jpg" (46)
"images.description" => "Copyright by Austin Cramer for DesignIntelligence. This is a placeholder while he makes new ones for us.,Copyright by Austin Cramer for DesignIntellig ... " (209)
"images.created" => "2018-04-12 15:28:55,2018-04-12 15:28:55" (39)
```

---

Additional fields and custom separator
```php
$finder = new RockFinder('template=person');
$f = $finder->addField('images', ['description', 'created']);
$f->alias = 'myalias';
$f->separator = ' ### ';
d($f->getObjects()[0]);
```
sql
```sql
SELECT
  `pages_id` AS `pageid`,
  GROUP_CONCAT(`data` ORDER BY `sort` SEPARATOR ' ### ') AS `myalias`,
  GROUP_CONCAT(`description` ORDER BY `sort` SEPARATOR ' ### ') AS `myalias.description`,
  GROUP_CONCAT(`created` ORDER BY `sort` SEPARATOR ' ### ') AS `myalias.created`
FROM `field_images` AS `myalias`
GROUP BY `pageid`
```
result
```php
stdClass #4d17
pageid => "1"
myalias => "airport_cartoon_3.jpg ### rough_cartoon_puppet.jpg" (50)
"myalias.description" => "Copyright by Austin Cramer for DesignIntelligence. This is a placeholder while he makes new ones for us. ### Copyright by Austin Cramer for DesignInte ... " (213)
"myalias.created" => "2018-04-12 15:28:55 ### 2018-04-12 15:28:55" (43)
```