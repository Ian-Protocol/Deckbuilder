<?php
session_start();
?>

<nav>
  <ul>
    <li><a href="index.php">Home</a></li>
    <li><a href="search_decks.php">Deck Search</a></li>
    <li><a href="create_deck.php">Create Deck</a></li>
  </ul>
  <?php if (isset($_SESSION['user_id'])): ?>
        <a href="logout.php">Logout (<?= $_SESSION['username'] ?>)</a>
    <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    <?php endif; ?>
</nav>