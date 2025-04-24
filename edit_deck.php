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
$deck_query = "SELECT d.deck_id, d.user_id, d.title, d.description, d.created_at, d.updated_at, d.archetype_id,
            i.image_id, i.regular_path, i.thumbnail_path,
            c.name,
            u.username,
            a.archetype
            FROM decks d
            LEFT OUTER JOIN images i
            ON d.deck_id = i.deck_id
            JOIN cards c
            ON d.card_id = c.card_id
            JOIN users u
            ON u.user_id = d.user_id
            JOIN archetypes a
            ON a.archetype_id = d.archetype_id
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

// Fetch deck data.
$statement = $db -> prepare($deck_query);
$statement -> bindValue('id', $id, PDO::PARAM_INT);
$statement -> execute();
$deck = $statement -> fetch();

// Fetch all cards.
$statement = $db -> prepare($cards_query);
$statement -> bindValue('id', $id, PDO::PARAM_INT);
$statement -> execute();
$cards = $statement -> fetchAll();

// Fetch archetypes
$query = "SELECT * FROM archetypes ORDER BY archetype ASC";
$statement = $db->prepare($query);
$statement->execute();
$archetypes = $statement->fetchAll();

$page_title = "Commander Deckbuilder - {$deck['title']}";

$deck_owner_id = $deck['user_id'];
$deck_owner_username = $deck['username'];
$deck_archetype_id = $deck['archetype_id'];
$can_edit = false;
$error_message = [];

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

    // Update quantities where applicable.
    // Raw data which will be sanitized in a loop below.
    $card_quantities = $_POST['card_quantities'];
    foreach ($cards as $card) {
        if (!is_null($card_quantities[$card['card_id']]) && is_numeric($card_quantities[$card['card_id']])) {
            $quantity = filter_var($card_quantities[$card['card_id']], FILTER_SANITIZE_NUMBER_INT);
        } else {
            $error_message[] = "Invalid quantity for card {$card['card_name']}.";
        }

        if (empty($error_message)) {
            if ($quantity != $card['quantity']) {
                if ($quantity > 0) {
                    $query = "UPDATE deck_cards SET quantity = :quantity
                            WHERE deck_id = :deck_id AND card_id = :card_id";
                    $statement = $db -> prepare($query);
                    $statement -> bindValue(':quantity', $quantity);
                    $statement -> bindValue(':deck_id', $id);
                    $statement -> bindValue(':card_id', $card['card_id']);
                    $statement -> execute();
                } else {
                    $query = "DELETE FROM deck_cards
                            WHERE deck_id = :deck_id AND card_id = :card_id";
                    $statement = $db -> prepare($query);
                    $statement -> bindValue(':deck_id', $id);
                    $statement -> bindValue(':card_id', $card['card_id']);
                    $statement -> execute();
                }
            }
        }
    }

    if ($_POST['description'] != $deck['description']) {
        if (strlen($_POST['description']) > 0) {
            $new_description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $query = "UPDATE decks SET description = :description
                                WHERE deck_id = :deck_id";
            $statement = $db -> prepare($query);
            $statement -> bindValue(':description', $new_description);
            $statement -> bindValue(':deck_id', $id);
            $statement -> execute();
        } else {
            $error_message[] = "Deck description is invalid";
        }
    }

    if ($_POST['archetype'] != $deck['archetype_id']) {
        if (is_numeric($_POST['archetype'])) {
            $new_archetype = filter_input(INPUT_POST, 'archetype', FILTER_SANITIZE_NUMBER_INT);
            $query = "UPDATE decks SET archetype_id = :archetype_id
                                WHERE deck_id = :deck_id";
            $statement = $db -> prepare($query);
            $statement -> bindValue(':archetype_id', $new_archetype);
            $statement -> bindValue(':deck_id', $id);
            $statement -> execute();
        } else {
            $error_message[] = "Deck archetype is invalid";
        }
    }

    if ($_POST['title'] != $deck['title']) {
        if (strlen($_POST['title']) > 0) {
            $new_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $query = "UPDATE decks SET title = :title
                                WHERE deck_id = :deck_id";
            $statement = $db -> prepare($query);
            $statement -> bindValue(':title', $new_title);
            $statement -> bindValue(':deck_id', $id);
            $statement -> execute();
        } else {
            $error_message[] = "Deck title is invalid";
        }
    }

    if ($image_delete_detected) {
        // Remove image from database.
        //TODO: Create a function for these to implement better code reuse.
        if (empty($error_message)) {
            $query = "DELETE FROM images WHERE image_id = :image_id";
            $statement = $db -> prepare($query);
            $statement -> bindValue(':image_id', $deck['image_id']);
            $statement -> execute();

            // Remove image from filesystem.
            unlink($deck['regular_path']);
            unlink($deck['thumbnail_path']);
        }
    } elseif ($image_upload_detected && !$upload_error_detected) {
        if (empty($error_message)) {
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
    }
    if (empty($error_message)) {
        header("Location: edit_deck.php?id={$id}");
        exit;
    }
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
        <header>
        <h1>Commander Deckbuilder - Edit Deck</h1>
        <?php include './includes/navbar.php'; ?>
        </header>
        <?php if (!empty($error_message)): ?>
            <div class="message">
                <?php foreach ($error_message as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <div id="register">
        <h1><?= $deck['title'] . " - by " . $deck_owner_username ?></h1>
        <form action="edit_deck.php?id=<?= $id ?>" method="post" enctype="multipart/form-data">
            <div id="deck-heading">
                <p>
                    <label for="title">Title:</label>
                    <input type="text" name="title" id="title" value="<?= $deck['title'] ?>">
                </p>
                <p>
                    <label for="archetype">Archetype:</label>
                    <select name="archetype" id="archetype">
                        <?php foreach ($archetypes as $archetype): ?>
                            <option value="<?= $archetype['archetype_id'] ?>" <?= $archetype['archetype_id'] == $deck_archetype_id ? 'selected' : '' ?>>
                                <?= ucwords($archetype['archetype']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <?php if ($deck['regular_path'] != ""): ?> 
                        <img src="<?= str_replace('\\', '/', $deck['regular_path']) ?>" alt="Deck Image" /><br>
                        <label for="delete-image">Delete Image</label>
                        <input name="delete-image" id="delete-image" type="checkbox"><br>
                        <label for="image">Update Image</label>
                    <?php else: ?>
                        <label for="image">Add Image</label>
                    <?php endif ?>
                    <input type="file" name="image" id="image">
                </p>
                <p>
                    <label for="description">Description:</label>
                    <textarea name="description" id="description"><?= $deck['description'] ?></textarea>
                </p>
            </div>
                <table>
                    <tr class="deck">
                        <th>Qty</th>
                        <th>Card Name</th>
                        <th>Mana Cost</th>
                    </tr>
                    <?php foreach ($cards as $card): ?>
                        <tr class="deck">
                            <td><input type="number" name="card_quantities[<?= $card['card_id'] ?>]" id="card_quantity_<?= $card['card_id'] ?>" min="0" value="<?= $card['quantity'] ?>"></td>
                            <td><?= $card['card_name'] ?></td>
                            <td><?= $card['mana_cost'] ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>
            <input type="submit" name="update" value="Update Deck">
            <input type="submit" name="delete" value="Delete Deck">
        </form>
        </div>
        <?php include './includes/footer.php'; ?>
    </body>
</html>