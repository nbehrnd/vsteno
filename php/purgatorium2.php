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
 
require_once "vsteno_template_top.php";
require_once "session.php";
require_once "dbpw.php";

/*
$safe_word = "";
$conn = null; 
$safe_submitted_by = ""; 
$safe_reviewed_by = ""; 
$safe_single_bas = ""; 
$safe_single_std = ""; 
$safe_separated_bas = "";
$safe_separated_std = ""; 
$safe_separated_prt = "";
$safe_recommended_form = "";
$safe_number_forms = "";
  */
  
function die_more_elegantly( $text ) {
        echo "$text";
        echo '<a href="aleph.php"><br><button>zurück</button></a><br><br>';   
        require_once "vsteno_template_bottom.php";
        die();
}

function StripOutWordSeparators( $word ) {
        $stripped = "";
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $character = mb_substr($word, $i, 1);
            if (($character !== "|") && ($character !== "\\")) $stripped .= $character;
        }
        return $stripped;
}

function GetWordID( $word ) {
    global $conn;
    $elysium = GetDBName( "elysium" );
   
    // check if word-entry exists already
    $sql = "SELECT * FROM $elysium WHERE word='$word'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        //echo "eintrag gefunden<br>";
        $row = $result->fetch_assoc();
        //echo "id = " . $row['word_id'] . "<br>";
        return $row['word_id'];
    } else {
        return 0; // no id (entry)
   }
}

function ConnectOrDie() {
    global $conn;
    // Create connection
    $conn = Connect2DB();
    // Check connection
    if ($conn->connect_error) {
        die_more_elegantly("Verbindung nicht möglich: " . $conn->connect_error . "<br>");
    }
}

function PrepareData() {
    global $safe_word, $conn, $safe_submitted_by, $safe_reviewed_by, $safe_single_bas, $safe_single_std, $safe_single_prt, $safe_separated_bas, $safe_separated_std, $safe_separated_prt,
           $safe_recommended_form, $safe_number_forms;
    // prepare data
    $safe_word = $conn->real_escape_string(StripOutWordSeparators($_POST['single_original']));
    //echo "Strippedout: $safe_word<br>";
    $safe_submitted_by = $_POST['submitted_by']; // htmlspecialchars($_POST['single_original']);
    //echo "safe_submitted_by = $safe_submitted_by<br>";
    $safe_reviewed_by = $conn->real_escape_string($_SESSION['user_id']);
    $safe_single_bas = ($_POST['single_chkcmp'] == "1") ? $conn->real_escape_string($_POST['single_txtcmp']) : "";
    $safe_single_std = ($_POST['single_chkstd'] == "1") ? $conn->real_escape_string($_POST['single_txtstd']) : "";
    $safe_single_prt = ($_POST['single_chkprt'] == "1") ? $conn->real_escape_string($_POST['single_txtprt']) : "";
    $safe_separated_bas = ($_POST['composed_chkcmp'] == "1") ? $conn->real_escape_string($_POST['composed_txtcmp']) : "";
    $safe_separated_std = ($_POST['composed_chkstd'] == "1") ? $conn->real_escape_string($_POST['composed_txtstd']) : "";
    $safe_separated_prt = ($_POST['composed_chkprt'] == "1") ? $conn->real_escape_string($_POST['composed_txtprt']) : "";
    if (isset($_POST['recommended_form'])) {
        $temp = ($_POST['recommended_form'] === "single") ? "1" : "2";
        $safe_recommended_form = $conn->real_escape_string($temp);
        $safe_number_forms = $conn->real_escape_string("2");
    } else {
        $safe_recommended_form = $conn->real_escape_string("1");
        $safe_number_forms = $conn->real_escape_string("1");
    }
    // echo "number: $safe_number_forms<br>";
    if ((mb_strlen($safe_separated_bas)==0) && (mb_strlen($safe_separated_std)==0) && (mb_strlen($safe_separated_prt)==0))  // empty strings in purgatorium can be used to mark word as single (if it has erroneously been marked as composed in training) 
        $safe_number_forms = $conn->real_escape_string("1");
    // echo "number: $safe_number_forms<br>";
}

function WriteDataToElysiumOrOlympus() {
     global $safe_word, $conn, $safe_submitted_by, $safe_reviewed_by, $safe_single_bas, $safe_single_std, $safe_single_prt, $safe_separated_bas, $safe_separated_std, $safe_separated_prt,
           $safe_recommended_form, $safe_number_forms;
    echo "<h1>Speichern</h1>";
    //echo "safe_reviewed_by = $safe_reviewed_by<br>";
    
    if ($_POST['decision_write_to_database'] == "1") {
        $word_id = GetWordID( $safe_word );
        $database_name = null;
        switch ($_POST['dest']) {
            case "elysium" : $database_name = GetDBName( "elysium" ); $destination = "Elysium"; break;
            case "olympus" : $database_name = GetDBName( "olympus" ); $destination = "Olympus"; break;
        }
        echo "Database_name: $database_name<br>";
        if ($word_id != 0) {
            // update existing entry
            $sql = "UPDATE $database_name 
            SET word='$safe_word', number_forms='$safe_number_forms', recommended_form='$safe_recommended_form', submitted_by='$safe_submitted_by', reviewed_by='$safe_reviewed_by', 
            single_bas='$safe_single_bas', single_std='$safe_single_std', single_prt='$safe_single_prt', separated_bas='$safe_separated_bas',
            separated_std='$safe_separated_std', separated_prt='$safe_separated_prt'
            WHERE word_id='$word_id';";
        } else {
        // create new entry
            $sql = "INSERT INTO $database_name (word, number_forms, recommended_form, submitted_by, reviewed_by, single_bas, single_std, single_prt, separated_bas, separated_std, separated_prt)
            VALUES ( '$safe_word', '$safe_number_forms', '$safe_recommended_form', '$safe_submitted_by', '$safe_reviewed_by', '$safe_single_bas', '$safe_single_std', '$safe_single_prt', '$safe_separated_bas',
            '$safe_separated_std', '$safe_separated_prt')";
        }
        $result = $conn->query($sql);
        echo "<p>Das Wort <b>$safe_word</b> wurde in $destination (<b>➟$database_name</b>) geschrieben.</p>";
        echo "<h2>Query:</h2><p><i>$sql</i></p>";
    } else echo "<p>Kein Eintrag in $destination vorgenommen.</p>";
}

function DeletePurgatoriumEntry() {
    global $safe_word, $conn;
    echo "<h1>Löschen</h1>";
    if ($_POST['decision_nirvana'] == "1") {
        $word_id = $_POST['word_id'];
        //echo "id = $word_id<br>";
        if ($word_id != 0) {
            // update existing entry
            $purgatorium = GetDBName( "purgatorium" );
            $sql = "DELETE FROM $purgatorium WHERE word_id='$word_id';"; 
        }
        $result = $conn->query($sql);
        echo "<p>Eintrag mit <b>id=$word_id</b> (<b>$safe_word</b>) wurde aus Purgatorium (<b>$purgatorium</b>) gelöscht.</p>";
        echo "<h2>Query:</h2><p><i>$sql</i></p>";
    } else echo "<p>Eintrag in Purgatorium nicht gelöscht.</p>";
}

// main
if (($_SESSION['user_logged_in']) && ($_SESSION['user_privilege'])) {

    echo "<h1>Verarbeiten</h1>";
         
    ConnectOrDie();
    PrepareData();
    WriteDataToElysiumOrOlympus();
    DeletePurgatoriumEntry();
    
    $conn->close();

    echo '<p><a href="purgatorium.php"><br><button>zurück</button></a></p><br>';   
    
} else {
    echo "<p>Sie benötigen Superuser-Rechte und müssen eingeloggt sein, um Aleph zu benutzen.</p>";
    echo '<a href="purgatorium.php"><br><button>zurück</button></a><br><br>';   
}   

require_once "vsteno_template_bottom.php";
    
?>