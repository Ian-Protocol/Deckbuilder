<?php
session_start();

$admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';

if (!$admin) {
    header("Location: index.php");
    exit;
}
// Connects to the database.
require './includes/connect.php';

$page_title = "Admin Control Panel";
$error_message = [];
$success_message = [];

$query = "SELECT * FROM decks";
$statement = $db -> prepare($query);
$statement -> execute(); 
$decks = $statement -> fetchAll();

$query = "SELECT * FROM comments";
$statement = $db -> prepare($query);
$statement -> execute(); 
$comments = $statement -> fetchAll();

$query = "SELECT * FROM users";
$statement = $db -> prepare($query);
$statement -> execute(); 
$users = $statement -> fetchAll();

if (isset($_POST['create-user'])) {
    // Sanitize user input to escape HTML entities and filter out dangerous characters.
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'];
    $password_reenter = $_POST['password-reenter'];

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

    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($error_message)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, password, email, role)
        VALUES (:username, :password, :email, :role)";
        $statement = $db -> prepare($query);
        $statement -> bindValue('username', $username);
        $statement -> bindValue('password', $password_hash);
        $statement -> bindValue('email', $email);
        $statement -> bindValue('role', $role);

        if ($statement -> execute()) {
            $success_message[] = "User created";
        } else {
            $error_message[] = "Error creating user";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <h1>Commander Deckbuilder - Admin Control Panel</h1>
        <?php include './includes/navbar.php'; ?>
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
            </div>
        <?php endif ?>
        <h2>User Control</h2>
        <h3>Create New User</h3>
        <form action="admin.php" method="post">
            <fieldset>
                <table>
                    <tr>
                        <td><label for="username">Username:</label><input type="text" id="username" name="username" required /></td>
                        <td><label for="role">Role:</label>
                        <select name="role" id="role">
                            <option value="registered">Registered</option>
                            <option value="admin">Admin</option>
                        </select></td>
                        <td><label for="email">Email:</label><input type="email" id="email" name="email" required /></td>
                        <td><label for="password">Password:</label><input type="password" id="password" name="password" required /></td>
                        <td><label for="password-reenter">Re-Enter Password:</label><input type="password" id="password-reenter" name="password-reenter" required /></td>
                        <td><input type="submit" name="create-user" value="Create User"></td>
                    </tr>
                </table>
            </fieldset>
        </form>
        <h3>Edit Existing User</h3>
        <form action="admin.php" method="post">
            <table>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Password</th>
                    <th>Re-Enter Password</th>
                    <th>Update</th>
                    <th>Delete</th>
                </tr>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><label for="username">Username:</label><input type="text" id="username" name="username" value="<?= $user['username'] ?>" /></td>
                        <td><label for="role">Role:</label>
                        <select name="role" id="role">
                            <option value="registered">Registered</option>
                            <option value="admin">Admin</option>
                        </select></td>
                        <td><label for="email">Email:</label><input type="email" id="email" name="email" /></td>
                        <td><label for="password">Password:</label><input type="password" id="password" name="password" /></td>
                        <td><label for="password-reenter">Re-Enter Password:</label><input type="password" id="password-reenter" name="password-reenter" /></td>
                        <td><input type="submit" name="update-user" value="Update"></td>
                        <td><input type="submit" name="delete-user" value="Delete"></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </form>
        <h2>Comment Control</h2>
        <h2>Deck Control</h2>
    </body>
    <?php include './includes/footer.php'; ?>
</html>