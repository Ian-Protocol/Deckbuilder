<?php
// Authenticates user
require './includes/authenticate.php';

// Connects to the database.
require './includes/connect.php';

$pageTitle = "Commander Deckbuilder - Create Deck";

// Define archetypes.
$archetypes = [
    "aggro",
    "aristocrats",
    "artifacts",
    "blink",
    "cascade",
    "chaos",
    "combo",
    "control",
    "counters",
    "enchantress",
    "group hug",
    "group slug",
    "infect",
    "kindred",
    "landfall",
    "lifegain",
    "midrange",
    "mill",
    "ramp",
    "reanimator",
    "spellslinger",
    "stax",
    "superfriends",
    "theft",
    "tokens",
    "voltron",
    "wheel"
];

if ($_POST) {
    //  Sanitize user input to escape HTML entities and filter out dangerous characters.
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // If title or content has less than 1 character, display error page.
    if (strlen($title) < 1 || strlen($content) < 1) {
        header("Location: error.php");
        exit;
    }
    
    //  Build the parameterized SQL query and bind to the above sanitized values.
    $query = "INSERT INTO blog_posts (title, content) VALUES (:title, :content)";
    $statement = $db -> prepare($query);
    
    //  Bind values to the parameters
    $statement -> bindValue(':title', $title);
    $statement -> bindValue(':content', $content);
    
    //  Execute the INSERT.
    //  execute() will check for possible SQL injection and remove if necessary
    if( $statement -> execute()) {
        header("Location: index.php");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <h1>Commander Deckbuilder - Create Deck</h1>
        <?php include './includes/navbar.php'; ?>
        <form action="create_deck.php" method="post" enctype="multipart/form-data">
            <fieldset>
                <legend>New Deck</legend>
                <p>
                    <label for="title">Title:</label>
                    <input name="title" id="title">
                </p>
                <p>
                    <label for="title">Commander:</label>
                    <input name="title" id="title">
                </p>
                <p>
                    <label for="content">Description:</label>
                    <textarea name="content" id="content"></textarea>
                </p>
                <p>
                    <label for="image">Image:</label>
                    <input type="file" name="image" id="image">
                </p>
                <P>
                    <label for="archetype">Archetype:</label>
                    <select name="archetype" id="archetype">
                        <option value="">-- Select Archetype --</option>
                        <?php foreach ($archetypes as $archetype): ?>
                            <option value="<?= $archetype ?>"><?= ucwords($archetype) ?></option>
                        <?php endforeach; ?>
                    </select> 
                </P>
                <p>
                    <label for="decklist">Deck List:</label>
                    <textarea name="decklist" id="decklist">Paste deck list here.</textarea>
                </p>
                <p>
                    <input type="submit" value="Submit">
                </p>
            </fieldset>
        </form>
    </body>
    <?php include './includes/footer.php'; ?>
</html>