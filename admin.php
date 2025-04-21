<?php
session_start();

$admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';

if (!$admin) {
    header("Location: index.php");
    exit;
}

$page_title = "Admin Control Panel";
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <h1>Commander Deckbuilder - Admin Control Panel</h1>
        <?php include './includes/navbar.php'; ?>
        <a href="admin_users.php"><h2>User Control</h2></a>
        <a href="admin_comments.php"><h2>Comment Control</h2></a>
        <a href="admin_decks.php"><h2>Deck Control</h2></a>
    </body>
    <?php include './includes/footer.php'; ?>
</html>