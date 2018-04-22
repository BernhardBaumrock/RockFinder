<?php

/**
 * ProcessHello.info.php
 * 
 * Return information about this module.
 *
 * If preferred, you can use a getModuleInfo() method in your module file, 
 * or you can use a ModuleName.info.json file (if you prefer JSON definition). 
 *
 */

$info = array(
  'title' => 'ProcessRockFinder',
  'summary' => 'ProcessModule to test RockFinders',
  'version' => 1,
  'author' => 'Bernhard Baumrock, baumrock.com',
  'icon' => 'bolt',
  'requires' => ['RockGrid'],

  'page' => array(
    'name' => 'rockfindertester',
    'parent' => 'setup',
    'title' => $this->_('RockFinder Tester'),
  ),
);
