<?php
session_start();
require 'includes/connect.php';

$page_title = "Commander Deckbuilder - Register";

if ($_POST) {
    // Sanitize user input to escape HTML entities and filter out dangerous characters.
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'];
    $password_reenter = $_POST['password-reenter'];
    $error_message = [];
    $success_message = [];

    // Validations:
    // No more than 1 underscore, hyphen, or space in a row.
    // No underscore, space, or hyphen at start or end.
    // Alphanumeric chars.
    $username_pattern = "/^[a-zA-Z0-9]+([a-zA-Z0-9](_|-| )[a-zA-Z0-9])*[a-zA-Z0-9]+$/";

    // 8 chars length, must contain at least 1 uppercase, 1 lowercase, 1 digit, 1 character.
    $password_pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/";

    if (!preg_match($username_pattern, $username)) {
        $error_message[] = "Username must not contain any special characters except for a single hyphen, underscore, or space.";
    }

    $username_query = "SELECT user_id FROM users WHERE username = :username";
    $statement = $db -> prepare($username_query);
    $statement -> bindValue('username', $username);
    $statement -> execute();

    if ($statement -> fetch()) {
        $error_message[] = "Username is already taken.";
    }

    $verified_email = filter_var($email, FILTER_VALIDATE_EMAIL);
    if (!$verified_email) {
        $error_message[] = "Email address is invalid.";
    }

    if (!preg_match($password_pattern, $password)) {
        $error_message[] = "Password must be at least 8 characters long and contain at least 1 lowercase letter, 1 uppercase letter, 1 digit, and 1 special character.";
    }

    if (strcmp($password, $password_reenter) != 0) {
        $error_message[] = "Passwords do not match. Try again.";
    }

    if (empty($error_message)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $role = "registered";
        $query = "INSERT INTO users (username, password, email, role)
        VALUES (:username, :password, :email, :role)";
        $statement = $db -> prepare($query);
        $statement -> bindValue('username', $username);
        $statement -> bindValue('password', $password_hash);
        $statement -> bindValue('email', $email);
        $statement -> bindValue('role', $role);

        if ($statement -> execute()) {
            $success_message[] = "Registration successful!";
        } else {
            $error_message[] = "Error registering user.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <header>
        <h1>Commander Deckbuilder - Register</h1>
        <?php include './includes/navbar.php'; ?>
        </header>
        <div id="register">
            <h2>Sign up to start building and enjoy the community!</h2>
            <form action="register.php" method="post">
                <label for="username">Username:</label><input type="text" id="username" name="username" required /><br>
                <label for="email">Email:</label><input type="email" id="email" name="email" required /><br>
                <label for="password">Password:</label><input type="password" id="password" name="password" required /><br>
                <label for="password-reenter">Re-Enter Password:</label><input type="password" id="password-reenter" name="password-reenter" required /><br>
                <input type="submit" value="Register">
            </form>
        </div>
        <?php if (!empty($error_message)): ?>
            <div id="message">
                <?php foreach ($error_message as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <?php if (!empty($success_message)): ?>
            <div id="message">
                <?php foreach ($success_message as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
                <a href="login.php">Login Here</a>
            </div>
        <?php endif ?>
        <?php include './includes/footer.php'; ?>
    </body>
</html>