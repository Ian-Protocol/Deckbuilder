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
    $archetype = $statement -> fetch();
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
?>

<!DOCTYPE html>
<html lang="en">
<?php include './includes/head.php'; ?>
<body>
<?php include './includes/navbar.php'; ?>

<h1><?= $archetype['archetype'] ?> Decks</h1>

<?php if (count($decks) === 0): ?>
    <p>No decks found for this archetype.</p>
<?php else: ?>
    <div id="featured">
        <h2>All Decks</h2>
        <table>
            <tr>
                <th></th>
                <th>Deck Name</th>
                <th>Commander</th>
                <th>Archetype</th>
                <th>Last Updated</th>
                <th>Created By</th>
            </tr>
            <?php foreach ($decks as $deck): ?>
                <tr>
                    <td>
                        <?php if (!empty($deck['thumbnail_path'])): ?>
                            <img src="<?= htmlspecialchars($deck['thumbnail_path']) ?>" alt="Deck Thumbnail" />
                        <?php endif; ?>
                    </td>
                    <td><a href="view_deck.php?id=<?= $deck['deck_id'] ?>"><?= htmlspecialchars($deck['title']) ?></a></td>
                    <td><?= htmlspecialchars($deck['name']) ?></td>
                    <td><?= htmlspecialchars($deck['archetype']) ?></td>
                    <td><?= htmlspecialchars($deck['updated_at']) ?></td>
                    <td><?= htmlspecialchars($deck['username']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<?php include './includes/footer.php'; ?>
</body>
</html>
