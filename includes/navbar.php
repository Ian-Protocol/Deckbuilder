<?php
$admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';

?>

<nav>
  <ul>
    <li><a href="index.php">Home</a></li>
    <li><a href="search_decks.php">View and Search Decks</a></li>
    <li><a href="create_deck.php">Create Deck</a></li>
    <?php if ($admin): ?>
      <li><a href="admin.php">Admin Control Panel</a></li>
    <?php endif ?>
  </ul>
  <?php if (isset($_SESSION['user_id'])): ?>
        <a href="logout.php">Logout (<?= $_SESSION['username'] ?>)</a>
    <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    <?php endif; ?>
</nav>