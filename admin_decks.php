<?php
session_start();
// TODO: Add ASC and DESC options for user.
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
$sort_message = "Sorted by last updated date (Default)";
$query_sort = "ORDER BY d.updated_at DESC";

// Delete a deck.
if (isset($_POST['delete-deck'])) {
    $deck_id = filter_input(INPUT_POST, "deck-id", FILTER_SANITIZE_NUMBER_INT);

    if (!empty($deck_id)) {
        $query = "DELETE FROM decks WHERE deck_id = :deck_id";
        $statement = $db -> prepare($query);
        $statement -> bindValue(':deck_id', $deck_id);
 
        if ($statement -> execute()) {
            $success_message[] = "Deck deleted";
        } else {
            $error_message[] = "Error deleting deck";
        }
    } else {
        $error_message[] = "Error deleting deck";
    }
}

// Edit a deck.
if (isset($_POST['edit-deck'])) {
    $deck_id = filter_input(INPUT_POST, "deck-id", FILTER_SANITIZE_NUMBER_INT);
    header("Location: edit_deck.php?id={$deck_id}");
}

// Sort decks.
if (isset($_POST['sort'])) {
    $sorting_method = filter_input(INPUT_POST, "sorting-method", FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    switch ($sorting_method) {
        case "title":
            $query_sort = "ORDER BY d.title ASC";
            $sort_message = "Sorted by Title";
            break;
        case "date-created":
            $query_sort = "ORDER BY d.created_at DESC";
            $sort_message = "Sorted by Date Created";
            break;
        case "date-updated":
            $query_sort = "ORDER BY d.updated_at DESC";
            $sort_message = "Sorted by Date Updated";
            break;
        case "username":
            $query_sort = "ORDER BY u.username ASC";
            $sort_message = "Sorted by Username";
            break;
    }
}

$query = "SELECT d.deck_id, d.title, d.created_at, d.updated_at, d.archetype_id, d.user_id,
        c.name,
        a.archetype_id, a.archetype,
        u.user_id, u.username,
        i.thumbnail_path
        FROM decks d
        JOIN cards c
        ON d.card_id = c.card_id
        JOIN archetypes a
        ON a.archetype_id = d.archetype_id
        JOIN users u
        ON u.user_id = d.user_id
        LEFT OUTER JOIN images i
        ON d.deck_id = i.deck_id
        " . $query_sort;
$statement = $db -> prepare($query);
$statement -> execute(); 
$decks = $statement -> fetchAll();
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
        <h2>Deck Control</h2>
        <form action="admin_decks.php" method="post">
            <h3>Sort Decks - <?= $sort_message ?></h3>
            <select name="sorting-method" id="sorting-method">
                <option value="title">Title</option>
                <option value="date-created">Date Created</option>
                <option value="date-updated">Date Updated</option>
                <option value="username">Username</option>
            </select>
            <input type="submit" name="sort" value="Sort Decks">
        </form>
        <table>
            <tr class="deck">
                <th></th>
                <th>Deck Name</th>
                <th>Archetype</th>
                <th>Date Updated</th>
                <th>Date Created</th>
                <th>Created By</th>
                <th colspan="2">Actions</th>
            </tr>
        <?php foreach ($decks as $deck): ?>
            <tr class="deck">
                <form action="admin_decks.php" method="post">
                    <input type="hidden" name="deck-id" value="<?= $deck['deck_id'] ?>">
                    <td>
                        <?php if ($deck['thumbnail_path']): ?>
                            <img src="<?= $deck['thumbnail_path'] ?>" alt="Deck Thumbnail" />
                        <?php endif ?>
                    </td>
                    <td>
                        <?= $deck['title'] ?>
                    </td>
                    <td>
                        <?= $deck['archetype'] ?>
                    </td>
                    <td>
                        <?= $deck['updated_at'] ?>
                    </td>
                    <td>
                        <?= $deck['created_at'] ?>
                    </td>
                    <td>
                        <?= $deck['username'] ?>
                    </td>
                    <td>
                        <input type="submit" name="edit-deck" value="Edit">
                        <input type="submit" name="delete-deck" value="Delete">
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </table>
        </form>
    </body>
    <?php include './includes/footer.php'; ?>
</html>