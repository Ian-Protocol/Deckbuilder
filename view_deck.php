<?php
// Redirect user back to index if id is not an integer.
if (isset($_GET['id']) && !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

// Connects to the database.
require('./includes/connect.php');

// Sanitize $_GET['id'] to ensure it's a number.
$id = filter_input(INPUT_GET, "id", FILTER_SANITIZE_NUMBER_INT);

// Build and prepare SQL String with :id placeholder parameter.
$deck_query = "SELECT d.deck_id, d.user_id, d.title, d.description, d.created_at, d.updated_at, d.archetype,
            i.regular_path,
            c.name
            FROM decks d
            JOIN images i
            ON d.deck_id = i.deck_id
            JOIN cards c
            ON d.card_id = c.card_id
            WHERE d.deck_id = :id LIMIT 1";

$cards_query = "SELECT d.deck_id,
                c.name AS card_name,
                c.type, c.mana_cost,
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
$can_edit = false;

// Only show "Edit Deck" button if user is admin or deck owner.
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $deck_owner_id) {
        $can_edit = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <h1>Commander Deckbuilder - <?= $deck['title'] ?></h1>
        <?php include './includes/navbar.php'; ?>
        <div id="deck-heading">
            <h2><?= $deck['name'] ?></h2>
            <?php if ($deck['regular_path'] != ""): ?>
                <img src="<?= $deck['regular_path'] ?>" alt="Deck Image" />
            <?php endif ?>
            <h3>Description</h3>
            <?= $deck['description'] ?>
            <?php if ($edit_deck): ?>
                <button type="button">Edit Deck</button>
            <?php endif ?>
        </div>
        <div id="deck">
            <ul>
                <?php foreach ($cards as $card): ?>
                    <li>
                        <?= $card['card_name'] . " " . $card['quantity'] ?>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    </body>
    <?php include './includes/footer.php'; ?>
</html>