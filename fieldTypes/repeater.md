# Examples for this fieldtype

Query all persons, show page-status, page-created, page-title and repeater. For the repeater add all fields (being "title" and "value" in this case):
```php
$finder = new RockFinder('template=person', ['status', 'created', 'title', 'repeater']);
$r = $finder->getField('repeater');
$r->addAllFields();
```
SQL
```sql
SELECT
  `pages`.`id` AS `id`,
  `status`.`status` AS `status`,
  `created`.`created` AS `created`,
  `title`.`title` AS `title`,
  `repeater`.`repeater` AS `repeater`,
  `repeater`.`title` AS `repeater.title`,
  `repeater`.`value` AS `repeater.value`
FROM
  `pages`

/* --- join status --- */
LEFT JOIN (SELECT `id` AS `pageid`, `status` FROM `pages` AS `status`) AS `status` ON `status`.`pageid` = `pages`.`id`
/* --- end status --- */


/* --- join created --- */
LEFT JOIN (SELECT `id` AS `pageid`, `created` FROM `pages` AS `created`) AS `created` ON `created`.`pageid` = `pages`.`id`
/* --- end created --- */


/* --- join title --- */
LEFT JOIN (SELECT
  `pages_id` AS `pageid`,
  `title`.`data` AS `title`
FROM `field_title` AS `title`) AS `title` ON `title`.`pageid` = `pages`.`id`
/* --- end title --- */


/* --- join repeater --- */
LEFT JOIN (SELECT
  `repeater`.`pages_id` AS `pageid`,
  `repeater`.`data` AS `repeater`,
  GROUP_CONCAT(`title`.`data` ORDER BY FIND_IN_SET(`title`.`pages_id`, `repeater`.`data`) separator ',') AS `title`,
  GROUP_CONCAT(`value`.`data` ORDER BY FIND_IN_SET(`value`.`pages_id`, `repeater`.`data`) separator ',') AS `value`
FROM `field_repeater` AS `repeater`
LEFT JOIN `field_title` AS `title` ON FIND_IN_SET(`title`.`pages_id`, `repeater`.`data`)
LEFT JOIN `field_value` AS `value` ON `value`.`pages_id` = `title`.`pages_id`
GROUP BY `repeater`.`pages_id`) AS `repeater` ON `repeater`.`pageid` = `pages`.`id`
/* --- end repeater --- */

WHERE
  `pages`.`id` IN (42023,42044,42045,42046,42047,42048,42049,42050,42051,42052,42053)
ORDER BY
  field(`pages`.`id`, 42023,42044,42045,42046,42047,42048,42049,42050,42051,42052,42053)
```
Result
```php
array (11)
0 => stdClass #c985
id => "42023" (5)
status => "1"
created => "2018-04-12 16:37:54" (19)
title => "maria" (5)
repeater => "42058,42056,42057" (17)
"repeater.title" => "drei,eins,zwei" (14)
"repeater.value" => "20,40,60" (8)
[...]
```

---

Adding specific fields of repeater-items:
```php
$r->addFields(['title']);
```

Result
```php
id => "42023" (5)
status => "1"
created => "2018-04-12 16:37:54" (19)
title => "maria" (5)
repeater => "42058,42056,42057" (17)
"repeater.title" => "drei,eins,zwei" (14)
```