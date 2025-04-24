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

$query = "SELECT c.*, u.username
FROM comments c
JOIN users u ON u.user_id = c.user_id
ORDER BY c.created_at DESC";
$statement = $db -> prepare($query);
$statement -> execute(); 
$comments = $statement -> fetchAll();

// Delete a comment.
if (isset($_POST['delete-comment'])) {
    $comment_id = filter_input(INPUT_POST, "comment-id", FILTER_SANITIZE_NUMBER_INT);

    if (!empty($comment_id)) {
        $query = "DELETE FROM comments WHERE comment_id = :comment_id";
        $statement = $db -> prepare($query);
        $statement -> bindValue(':comment_id', $comment_id);
 
        if ($statement -> execute()) {
            $success_message[] = "Comment deleted";
        } else {
            $error_message[] = "Error deleting comment";
        }
    } else {
        $error_message[] = "Error deleting comment";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <header>
        <h1>Admin Control Panel</h1>
        <?php include './includes/navbar.php'; ?>
        </header>
        <?php if (!empty($error_message)): ?>
            <div class="message">
                <?php foreach ($error_message as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <?php if (!empty($success_message)): ?>
            <div class="message">
                <?php foreach ($success_message as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <h2>Comment Control</h2>
        <table>
            <tr class="deck">
                <th>Username</th>
                <th>Content</th>
                <th>Date Posted</th>
                <th>Actions</th>
            </tr>
        <?php foreach ($comments as $comment): ?>
            <tr class="deck">
                <form action="admin_comments.php" method="post">
                    <input type="hidden" name="comment-id" value="<?= $comment['comment_id'] ?>">
                    <td>
                        <?= htmlspecialchars($comment['username']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($comment['content']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($comment['created_at']) ?>
                    </td>
                    <td>
                        <input type="submit" name="delete-comment" value="Delete">
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </table>
        </form>
    <?php include './includes/footer.php'; ?>
    </body>
</html>