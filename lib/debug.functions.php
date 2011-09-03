<?php 

// Dump the contents of a variable into HTML comments for debugging:
function debugVar ($var) {
  global $CFG;
  
  if ($CFG->debug) {
    echo "\n<!--\nDEBUG INFO: \n";
    if (is_array ($var))
      print_r ($var);
    else
      var_dump ($var);
    echo "\n-->\n";
  }
}

// Dump function parameters into HTML comments for debugging:
function debugFunc () {
  global $CFG;

  if ($CFG->debug) {
    $argv = func_get_args ();
    echo "\n<!--\nDEBUG INFO: \n";
    print_r ($argv);
    echo "\n-->\n";
  }
}

// Dump a debugging message into HTML comments for debugging:
function debugMsg ($msg) {
  global $CFG;

  if ($CFG->debug) {
    echo "\n<!--\nDEBUG INFO: \n";
    echo $msg;
    echo "\n-->\n";
  }
} 

?>