<?php
$finder = new \ProcessWire\RockFinder('id>0, limit=5', ['title', 'status']);

return $finder->getSQL();
