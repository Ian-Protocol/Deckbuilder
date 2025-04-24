<?php
session_start();
require './includes/connect.php';

$archetype_id = filter_input(INPUT_GET, 'archetype', FILTER_VALIDATE_INT);

if ($archetype_id) {
    // Validate archetype
    $query = "SELECT * FROM archetypes WHERE archetype_id = :archetype_id";
    $statement = $db -> prepare($query);
    $statement -> bindValue(':archetype_id', $archetype_id);
    $statement -> execute();
    $selected_archetype = $statement -> fetch();
} else {
    // TODO: Error here.
}

// Fetch decks by archetype.
$query = "SELECT d.deck_id, d.title, d.updated_at, u.username,
           c.name, a.archetype, i.thumbnail_path
    FROM decks d
    JOIN users u ON d.user_id = u.user_id
    JOIN cards c ON d.card_id = c.card_id
    JOIN archetypes a ON d.archetype_id = a.archetype_id
    LEFT JOIN images i ON d.deck_id = i.deck_id
    WHERE d.archetype_id = :archetype_id
    ORDER BY d.updated_at DESC";
$statement = $db -> prepare($query);
$statement -> bindValue(':archetype_id', $archetype_id);
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
    <h2><?= ucwords($selected_archetype['archetype']) ?> Decks</h2>
    <?php if (count($decks) === 0): ?>
        <p><h3>No decks found for this archetype.</h3></p>
    <?php else: ?>
    <div id="featured">
        <table>
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
                        <?php if (!empty($deck['thumbnail_path'])): ?>
                            <img src="<?= $deck['thumbnail_path'] ?>" alt="Deck Thumbnail" />
                        <?php endif; ?>
                    </td>
                    <td><a href="view_deck.php?id=<?= $deck['deck_id'] ?>"><?= $deck['title'] ?></a></td>
                    <td><?= $deck['name'] ?></td>
                    <td><?= ucwords($deck['archetype']) ?></td>
                    <td><?= $deck['updated_at'] ?></td>
                    <td><?= $deck['username'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<?php include './includes/footer.php'; ?>
</body>
</html>
