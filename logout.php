<?php
session_start();
$_SESSION = [];

$page_title = "Commander Deckbuilder - Log Out";
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <header>
        <h1>Commander Deckbuilder - Log Out</h1>
        <?php include './includes/navbar.php'; ?>
        </header>
        <div id="register">
            <h2>Farewell 4{W}{W}</h2>
            You are logged out. <a href="index.php">Return to home page</a>
        </div>
    </body>
    <?php include './includes/footer.php'; ?>
</html>