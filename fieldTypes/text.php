<?php namespace ProcessWire;
// returns a regular textfield value
// fields [pageid, fieldname]
// for the join we only select fieldname
// the resulting fields name will be fieldname

/*

resulting table:
 pageid | data           |
--------------------------
      1 | my page title  |

after join:
 id | title            |
------------------------
  1 | my page title    |

*/
class RockFinderFieldText extends RockFinderField {
  
}