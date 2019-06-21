<?php
/* VSTENO - Vector Steno Tool with Enhanced Notational Options
 * (c) 2018 - Marcel Maci (m.maci@gmx.ch)
 
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program. If not, see <http://www.gnu.org/licenses/>.
 */
 
 
/*
14. August 2018: New rule formalism

Either:    A => B
Or:        A => array(a, b, c, d ...)
where:     a is the original B (i.e. replacement for REGEX)
           b, c, d ... are exceptions: if one of the matches, the rule won't be applied
           
           can be used for example for word "geschäft": 
           define a rule which replaces "schäft" => "{SCHAFT}"
           define exception "geschäft" (rule shouldn't be applied to that word)

           in PHP:
           "schaft$ => array( "{SCHAFT}", "Geschäft(en?)?" )

           Advantages:
           (1) exceptions can be indicated together with rules (more logical and better to understand)
           (2) possible to use REGEX also for exceptions (big plus)
           
           Performance-whise this should also be beneficial since exceptions are only tested if first part of rule matches
           (in the old version, every exception was tested on every word).
*/
 
// 25. September 2018: read data from database

require_once "session.php";
//echo "Model = " . $_SESSION['actual_model'] . "<br>";
require_once "import_model.php";
require_once "engine.php";
require_once "parser.php";
//require_once "regex_helper_functions.php";

global $font, $combiner, $shifter;
global $rules, $functions_table;
global $insertion_key;
global $global_error_string;
$global_error_string = "";

// main

// ok, when I implemented data.php (which evolved from php-file with data as variables included to
// and include that loaded the same variables from db via a parser) I was too lazy to implement a proper
// "import_from_database"-function ... As a result, data.php relied on session-variables to load the actual
// model. This worked fine as long as only one model was used (and the model never change). Now, with two
// models (german and spanish), this "autoloading via include" makes it impossible to switch between the
// models since whenever you include data.php the models gets loaded before you can change it. Which means:
// you cannot start the calculation (via input form) and then change the model according to what you selected
// in the input form. The fastest and easiest (= with as little work as possible) way around that for the moment
// is to "intercept" the post-variable in data.php in order to change the session-variables beforehand
// (pretending the model has already been selected before the form is evaluated).
// Of course: This is not good programming style at all ... (looks more like BASIC with spaghetti-code-gotos here
// and there ... :)

// adjust session variable so that correct model gets loaded
// session-variable is also used to set correct rules pointers for regex-parser-functions!!!
if (!(isset($_SESSION['actual_model']))) $_SESSION['actual_model'] = $default_model;
$model_to_load = $_SESSION['actual_model'];

// DO NOT ECHO DEBUG INFORMATION HERE => THATS BEFORE HTML HEAD!!!!!!!!!!
$text_to_parse = LoadModelFromDatabase($model_to_load);
//echo "text: $text_to_parse<br><br>";

$test = ImportModelFromText($text_to_parse);
$actual_model = $_SESSION['actual_model'];

?>