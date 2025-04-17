<?php
session_start();
// Redirect user back to index if id is not an integer.
if (isset($_GET['id']) && !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

// Sanitize $_GET['id'] to ensure it's a number.
$deck_id = filter_input(INPUT_GET, "id", FILTER_SANITIZE_NUMBER_INT);
$can_comment = false;

// If the user is not logged in, they can still view this page.
if (!isset($_SESSION['user_id'])) {
    // Something
} else {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $can_comment = true;
}

// Connects to the database.
require('./includes/connect.php');

// Build and prepare SQL String with :id placeholder parameter.
$deck_query = "SELECT d.deck_id, d.user_id, d.title, d.description, d.created_at, d.updated_at, d.archetype_id,
            i.regular_path,
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
                c.type, c.mana_cost,
                dc.quantity
                FROM decks d
                JOIN deck_cards dc
                ON d.deck_id = dc.deck_id
                JOIN cards c
                ON dc.card_id = c.card_id
                WHERE d.deck_id = :id";

$comments_query = "SELECT d.deck_id,
                c.user_id, c.content, c.created_at,
                u.user_id, u.username
                FROM decks d
                JOIN comments c
                ON d.deck_id = c.deck_id
                JOIN users u
                ON u.user_id = c.user_id
                WHERE d.deck_id = :id
                ORDER BY c.created_at DESC";

// A PDO::Statement is prepared from the query.
$statement = $db -> prepare($deck_query);
$statement -> bindValue('id', $deck_id, PDO::PARAM_INT);
$statement -> execute();

// Fetch the row selected by primary key id.
$deck = $statement -> fetch();

// A PDO::Statement is prepared from the query.
$statement = $db -> prepare($cards_query);
$statement -> bindValue('id', $deck_id, PDO::PARAM_INT);
$statement -> execute();

// Fetch the row selected by primary key id.
$cards = $statement -> fetchAll();

// A PDO::Statement is prepared from the query.
$statement = $db -> prepare($comments_query);
$statement -> bindValue('id', $deck_id, PDO::PARAM_INT);
$statement -> execute();

// Fetch the row selected by primary key id.
$comments = $statement -> fetchAll();

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

if ($_POST) {
    //  Sanitize user input to escape HTML entities and filter out dangerous characters.
    $content = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (strlen($content) > 0) {
        $query = "INSERT INTO comments (deck_id, user_id, content) 
            VALUES (:deck_id, :user_id, :content)";

        $statement = $db -> prepare($query);
        $statement -> bindValue(':deck_id', $deck_id);
        $statement -> bindValue(':user_id', $user_id);
        $statement -> bindValue(':content', $content);
        $statement -> execute();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <h1>Commander Deckbuilder - <?= $deck['title'] . " - by " . $deck_owner_username ?></h1>
        <?php include './includes/navbar.php'; ?>
        <div id="deck-heading">
            <h2><?= $deck['name'] ?></h2>
            <?php if ($can_edit): ?>
                <a href="edit_deck.php?id=<?= $deck['deck_id'] ?>">Edit Deck</a>
            <?php endif ?>
            <?php if ($deck['regular_path'] != ""): ?>
                <img src="<?= $deck['regular_path'] ?>" alt="Deck Image" />
            <?php endif ?>
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
                        <td><?= $card['quantity'] ?></td>
                        <td><?= $card['card_name'] ?></td>
                        <td><?= $card['mana_cost'] ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
        <?php if ($comments): ?>
            <section id="comments">
                <h3>Comments</h3>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-header">
                            <?= $comment['username'] ?>
                            <?= $comment['created_at'] ?>
                        </div>
                        <?= $comment['content'] ?>
                    </div>
                <?php endforeach ?>
            </section>
        <?php endif ?>
        <?php if ($can_comment): ?>
            <form action="view_deck.php?id=<?= $deck_id ?>" method="post">
                <fieldset>
                    <label for="comment"><h3>Leave a Comment</h3></label>
                    <textarea name="comment" id="comment"></textarea>
                    <input type="submit" value="Submit">
                </fieldset>
            </form>
        <?php endif ?>
    </body>
    <?php include './includes/footer.php'; ?>
</html>