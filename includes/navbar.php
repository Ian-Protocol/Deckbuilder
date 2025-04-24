<?php
$admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';

?>

<div id="main-navigation">
  <nav aria-label="main navigation">
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="search_decks.php">View Decks</a></li>
        <li><a href="create_deck.php">Create Deck</a></li>
        <?php if ($admin): ?>
          <li><a href="admin.php">Admin Control Panel</a></li>
        <?php endif ?>
        <?php if (isset($_SESSION['user_id'])): ?>
          <li><a href="logout.php">Logout (<?= $_SESSION['username'] ?>)</a></li>
        <?php else: ?>
          <li><a href="login.php">Login</a></li>
          <li><a href="register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </nav>
</div>