<?php
// Connects to the database.
require('./includes/connect.php');

$page_title = "Commander Deckbuilder";

// SQL is written as a String.
// $query = "SELECT * FROM blog_posts ORDER BY date DESC LIMIT 5";

// A PDO::Statement is prepared from the query.
// $statement = $db->prepare($query);

// Execution on the DB server is delayed until we execute().
// $statement->execute(); 
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