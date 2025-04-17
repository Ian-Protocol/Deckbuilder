<?php
session_start();
// Redirect user back to index if id is not an integer.
if (isset($_GET['id']) && !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

// Sanitize $_GET['id'] to ensure it's a number.
$id = filter_input(INPUT_GET, "id", FILTER_SANITIZE_NUMBER_INT);

// If the user is not logged in, they can still view the deck.
if (!isset($_SESSION['user_id'])) {
    header("Location: view_deck.php?id={$id}");
    exit;
} else {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
}

// Connects to the database.
require './includes/connect.php';

// Image resizing and uploading.
require './includes/images.php';

// Build and prepare SQL String with :id placeholder parameter.
$deck_query = "SELECT d.deck_id, d.user_id, d.title, d.description, d.created_at, d.updated_at, d.archetype,
            i.image_id, i.regular_path, i.thumbnail_path,
            c.name,
            u.username
            FROM decks d
            LEFT OUTER JOIN images i
            ON d.deck_id = i.deck_id
            JOIN cards c
            ON d.card_id = c.card_id
            JOIN users u
            ON u.user_id = d.user_id
            WHERE d.deck_id = :id LIMIT 1";

$cards_query = "SELECT d.deck_id,
                c.name AS card_name,
                c.type, c.mana_cost, c.card_id,
                dc.quantity
                FROM decks d
                JOIN deck_cards dc
                ON d.deck_id = dc.deck_id
                JOIN cards c
                ON dc.card_id = c.card_id
                WHERE d.deck_id = :id";

// A PDO::Statement is prepared from the query.
$statement = $db -> prepare($deck_query);
$statement -> bindValue('id', $id, PDO::PARAM_INT);
$statement -> execute();

// Fetch the row selected by primary key id.
$deck = $statement -> fetch();

// A PDO::Statement is prepared from the query.
$statement = $db -> prepare($cards_query);
$statement -> bindValue('id', $id, PDO::PARAM_INT);
$statement -> execute();

// Fetch the row selected by primary key id.
$cards = $statement -> fetchAll();

$page_title = "Commander Deckbuilder - {$deck['title']}";

$deck_owner_id = $deck['user_id'];
$deck_owner_username = $deck['username'];
$can_edit = false;

// Only show "Edit Deck" button if user is admin or deck owner.
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $deck_owner_id) {
        $can_edit = true;
    }
}

// Update or delete deck.
if (isset($_POST['update'])) {
    $image_upload_detected = isset($_FILES['image']) && ($_FILES['image']['error'] === 0);
    $upload_error_detected = isset($_FILES['image']) && ($_FILES['image']['error'] > 0);
    $image_delete_detected = isset($_POST['delete-image']) && $_POST['delete-image'] === "on";

    if ($image_delete_detected) {
        // Remove image from database.
        //TODO: Create a function for these to implement better code reuse.
        $query = "DELETE FROM images WHERE image_id = :image_id";
        $statement = $db -> prepare($query);
        $statement -> bindValue(':image_id', $deck['image_id']);
        $statement -> execute();

        // Remove image from filesystem.
        unlink($deck['regular_path']);
        unlink($deck['thumbnail_path']);
    } elseif ($image_upload_detected && !$upload_error_detected) {
        // Clear previous image.
        $query = "DELETE FROM images WHERE image_id = :image_id";
        $statement = $db -> prepare($query);
        $statement -> bindValue(':image_id', $deck['image_id']);
        $statement -> execute();

        // Remove previous image from filesystem.
        unlink($deck['regular_path']);
        unlink($deck['thumbnail_path']);

        // Upload new image.
        upload_image($db, $id);
    }
    header("Location: edit_deck.php?id={$id}");
    exit;
} elseif (isset($_POST['delete'])) {
    $query = "DELETE FROM decks WHERE deck_id = :id";
    $statement = $db -> prepare($query);
    $statement -> bindValue(':id', $id, PDO::PARAM_INT);
    $statement -> execute();

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <h1>Commander Deckbuilder - <?= $deck['title'] . " - by " . $deck_owner_username ?></h1>
        <?php include './includes/navbar.php'; ?>
        <form action="edit_deck.php?id=<?= $id ?>" method="post" enctype="multipart/form-data">
            <div id="deck-heading">
                <h2><?= $deck['name'] ?></h2>
                <?php if ($deck['regular_path'] != ""): ?>
                    <img src="<?= $deck['regular_path'] ?>" alt="Deck Image" />

                    <label for="delete-image">Delete Image</label>
                    <input name="delete-image" id="delete-image" type="checkbox">
                    <label for="image">Update Image</label>
                <?php else: ?>
                    <label for="image">Add Image</label>
                <?php endif ?>
                <input type="file" name="image" id="image">
                <h3>Description</h3>
                <?= $deck['description'] ?>
            </div>
            <div id="deck">
                <table>
                    <tr>
                        <th>Qty</th>
                        <th>Card Name</th>
                        <th>Mana Cost</th>
                    </tr>
                    <?php foreach ($cards as $card): ?>
                        <tr>
                            <td><input type="number" name="card_quantities[<?= $card['card_id'] ?>]" id="card_quantity_<?= $card['card_id'] ?>" min="0" value="<?= $card['quantity'] ?>"></td>
                            <td><?= $card['card_name'] ?></td>
                            <td><?= $card['mana_cost'] ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </div>
            <input type="submit" name="update" value="Update Deck">
            <input type="submit" name="delete" value="Delete Deck">
        </form>
    </body>
    <?php include './includes/footer.php'; ?>
</html>