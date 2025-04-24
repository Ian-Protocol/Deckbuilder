<?php
session_start();
require 'includes/connect.php';

$page_title = "Commander Deckbuilder - Log In";
$messages = [];
$login_successful = false;

if ($_POST) {
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $password_raw = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = :username";
    $statement = $db -> prepare($query);
    $statement -> bindValue('username', $username);
    $statement -> execute();

    $user = $statement -> fetch() ?? null;

    if ($user) {
        $password_hash = $user['password'];

        if (password_verify($password_raw, $password_hash)) {
            $messages[] = "Login successful!";
            $login_successful = true;

            // Set session values.
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
        } else {
            $messages[] = "Incorrect password. Please try again.";
        }
    } else {
        $messages[] = "User not found. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <header>
        <h1>Commander Deckbuilder - Log In</h1>
        <?php include './includes/navbar.php'; ?>
        </header>
        <div id="register">
            <h2>Log in to start building and enjoy the community!</h2>
            <form action="login.php" method="post">
                <label for="username">Username:</label><input type="text" id="username" name="username" required />
                <label for="password">Password:</label><input type="password" id="password" name="password" required />
                <input type="submit" value="Log In">
            </form>
        </div>
        <div class="message">
            <?php if ($login_successful): ?>
                <?php foreach ($messages as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
                <a href="index.php">Go to homepage</a>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
            <?php endif ?>
        </div>
        <?php include './includes/footer.php'; ?>
    </body>
</html>