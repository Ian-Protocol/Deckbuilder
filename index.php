<?php
session_start();
// Connects to the database.
require('./includes/connect.php');

$page_title = "Commander Deckbuilder";

// TODO: Could fetch as I display them, what's better?
$query = "SELECT d.deck_id, d.title, d.updated_at, d.archetype_id,
            c.name,
            a.archetype_id, a.archetype,
            i.thumbnail_path
FROM decks d
JOIN cards c
ON d.card_id = c.card_id
JOIN archetypes a
ON a.archetype_id = d.archetype_id
LEFT OUTER JOIN images i
ON d.deck_id = i.deck_id
ORDER BY updated_at DESC LIMIT 5";

$statement = $db -> prepare($query);
$statement -> execute(); 
$decks = $statement -> fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <h1>Commander Deckbuilder</h1>
        <?php include './includes/navbar.php'; ?>
        <div id="featured">
            <h2>Recent Decks</h2>
            <table>
                <tr>
                    <th></th>
                    <th>Deck Name</th>
                    <th>Commander</th>
                    <th>Archetype</th>
                    <th>Last Updated</th>
                </tr>
                <?php foreach ($decks as $deck): ?>
                    <tr>
                        <td>
                            <?php if ($deck['thumbnail_path']): ?>
                                <img src="<?= $deck['thumbnail_path'] ?>" alt="Deck Thumbnail" />
                            <?php endif ?>
                        </td>
                        <td><a href="view_deck.php?id=<?= $deck['deck_id'] ?>"><?= $deck['title'] ?></a></td>
                        <td><?= $deck['name'] ?></td>
                        <td><?= ucwords($deck['archetype']) ?></td>
                        <td><?= $deck['updated_at'] ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
    </body>
    <?php include './includes/footer.php'; ?>
</html>