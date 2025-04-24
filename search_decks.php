<?php
session_start();
// Connects to the database.
require('./includes/connect.php');

$page_title = "Commander Deckbuilder - View and Search Decks";

// TODO: Could fetch as I display them, what's better?
$query = "SELECT d.deck_id, d.title, d.updated_at, d.archetype_id, d.user_id,
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
ORDER BY d.updated_at DESC";

$statement = $db -> prepare($query);
$statement -> execute(); 
$decks = $statement -> fetchAll();

// Fetch archetypes
$query = "SELECT * FROM archetypes ORDER BY archetype ASC";
$statement = $db->prepare($query);
$statement->execute();
$archetypes = $statement->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <header>
        <h1>Commander Deckbuilder - View Decks</h1>
        <?php include './includes/navbar.php'; ?>
        </header>
        <div id="register">
        <h1>Browse by Archetype</h1>
        <form action="archetype.php" method="get">
            <select id="archetype" name="archetype">
                <?php foreach ($archetypes as $archetype): ?>
                        <option value="<?= $archetype['archetype_id'] ?>"><?= ucwords($archetype['archetype']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Go">
        </form>
        </div>
        <div id="featured">
            <table>
                <caption><h2>All Decks</h2></caption>
                <tr class="deck">
                    <th></th>
                    <th>Deck Name</th>
                    <th>Commander</th>
                    <th>Archetype</th>
                    <th>Last Updated</th>
                    <th>Created By</th>
                </tr>
                <?php foreach ($decks as $deck): ?>
                    <tr class="deck">
                        <td>
                            <?php if ($deck['thumbnail_path']): ?>
                                <img src="<?= $deck['thumbnail_path'] ?>" alt="Deck Thumbnail" />
                            <?php endif ?>
                        </td>
                        <td><a href="view_deck.php?id=<?= $deck['deck_id'] ?>"><?= $deck['title'] ?></a></td>
                        <td><?= $deck['name'] ?></td>
                        <td><?= ucwords($deck['archetype']) ?></td>
                        <td><?= $deck['updated_at'] ?></td>
                        <td><?= $deck['username'] ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
    </body>
    <?php include './includes/footer.php'; ?>
</html>