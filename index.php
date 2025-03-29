<?php
// Connects to the database.
require('./includes/connect.php');

$page_title = "Commander Deckbuilder";

$query = "SELECT title FROM decks ORDER BY updated_at DESC LIMIT 10";
$statement = $db -> prepare($query);
$statement -> execute(); 
$decks = $statement -> fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <h1>Commander Deckbuilder</h1>
        <?php include './includes/navbar.php'; ?>
        <div id="featured">
            <h2>Featured Decks</h2>
        </div>
    </body>
    <?php include './includes/footer.php'; ?>
</html>