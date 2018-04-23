<?php
$info = [
  'title' => 'RockFinder',
  'version' => 2,
  'summary' => 'Highly Efficient and Flexible SQL Finder Module to return page data without loading PW pages into memory',
  'singular' => true, 
  'autoload' => false, 
  'icon' => 'search',
  'requires' => [
    'ProcessWire>=3.0.46', // we need the findIDs() method
  ],
];