<?php
// TODO: Create better authentication.
// Authenticates user
require './includes/authenticate.php';

// Connects to the database.
require './includes/connect.php';

$page_title = "Commander Deckbuilder - Create Deck";

// TODO: Figure out users.
// Perhaps with sessions! $user_id = $_SESSION['user_id'];
// Admin is id 1.
$user_id = 1;

// Define archetypes.
// Considerations:
// lands matter could fit into landfall/ramp?
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
    "graveyard",
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
    "storm",
    "superfriends",
    "theft",
    "tokens",
    "voltron",
    "wheel"
];

// Parses the deck list from the pasted or uploaded text.
function parse_decklist($decklist_raw) {
    $lines = explode("\n", $decklist_raw);
    $deck = [];

    // https://regex101.com/
    // Regex to match most card names with known special chars excluding brackets
    // which are output by some deckbuilders or MTG programs. Update if necessary.
    // May not match some "un" set names but those are illegal in EDH.
    // Don't currently check legality though, perhaps something to do in future.
    $card_pattern = '/^(?<quantity>\d+)[xX]?\s+(?<name>[a-zA-Z0-9 \/,:-]*)/';

    foreach ($lines as $line) {
        $line = trim($line);

        if (preg_match($card_pattern, $line, $matches)) {
            $quantity = intval($matches['quantity']);
            $card_name = trim($matches['name']);

            if (isset($deck[$card_name])) {
                // If card exists, increase its quantity.
                $deck[$card_name] += $quantity;
            } else {
                // If it doesn't, set it.
                $deck[$card_name] = $quantity;
            }
        }
    }

    return $deck;
}

// Saves the deck list in the decks table.
function save_deck($db, $title, $description, $commander_id, $archetype, $user_id) {
        // Query & binding.
        //  Build the parameterized SQL query and bind to the sanitized values.
        // TODO: Create a dynamic version like in fetch_card.
        $query = "INSERT INTO decks (user_id, title, description, archetype, card_id) 
        VALUES (:user_id, :title, :description, :archetype, :card_id)";
        $statement = $db -> prepare($query);

        //  Bind values to the parameters
        $statement -> bindValue(':user_id', $user_id);
        $statement -> bindValue(':title', $title);
        $statement -> bindValue(':description', $description);
        $statement -> bindValue(':archetype', $archetype);
        $statement -> bindValue(':card_id', $commander_id);

        //  Execute the INSERT.
        //  execute() will check for possible SQL injection and remove if necessary
        // TODO: Prepare deck view, then go to deck view instead of index.
        // header("Location: view_deck.php?id=" . $deck_id);
        // but do it upon return.
        $statement -> execute();
        
        $deck_id = $db -> lastInsertId();

        return $deck_id;
}

function find_card($db, $card_name) {
    $query = "SELECT id FROM cards WHERE name = :card_name";
    $statement = $db->prepare($query);

    $statement -> bindValue(':card_name', $card_name);

    $statement -> execute();

    if ($$statement) {
        return $statement['id'];
    } else {
        return fetch_card($db, $card_name);
    }
}

// Fetches the card from API.
// Fetching a new card always inserts into database.
function fetch_card($db, $card_name) {
    $encoded_name = urlencode($card_name);
    $fetchURL = 'https://api.magicthegathering.io/v1/cards?name=' . $encoded_name;

    $json = file_get_contents($fetchURL);

    if ($json) {
        $card_data = json_decode($json, true);
    }
    // TODO: Else: handle error here.

    if (!empty($card_data['cards'])) {
        // Acquire first result (sometimes duplicates exist).
        $card = $card_data['cards'][0];

        $columns = [
            'name' => $card['name'],
            'type' => $card['type'],
            'mana_cost' => $card['manaCost'] ?? 0,
            'text' => $card['text'],
            'power' => isset($card['power']) ? $card['power'] : null,
            'toughness' => isset($card['toughness']) ? $card['toughness'] : null,
            'image_url' => $card['imageUrl'],
            'cmc' => $card['cmc']
        ];

        // Create query.
        // Separates each key by its delimiter.
        $query = "INSERT INTO cards (" . implode(", ", array_keys($columns)) . ") 
            VALUES (" . implode(", :", array_keys($columns)) . ")";

        $statement = $db -> prepare($query);

        // Binds each value.
        foreach ($columns as $key => $value) {
            $statement -> bindValue(":$key", $value);
        }

        $statement -> execute();

        $card_id = $db -> lastInsertId();

        return $card_id;
    }
    // TODO: Else: handle error here.
    // return null?
}

if ($_POST) {
    //  Sanitize user input to escape HTML entities and filter out dangerous characters.
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $commander_name = filter_input(INPUT_POST, 'commander', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $archetype = filter_input(INPUT_POST, 'archetype', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $decklist_raw = filter_input(INPUT_POST, 'decklist', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // TODO: Add better error processing.
    if (!in_array($archetype, $archetypes)) {
        header("Location: error.php");
        exit;
    }

    // TODO: Add better error processing.
    // If title or content has less than 1 character, display error page.
    if (strlen($title) < 1 || strlen($commander_name) < 1) {
        header("Location: error.php");
        exit;
    }

    // TODO: Add better error processing.
    if (empty($decklist_raw)) {
        header("Location: error.php");
        exit;
    }

    // Find commander id.
    $commander_id = find_card($db, $commander_name);

    // Parse decklist
    $decklist = parse_decklist($decklist_raw);

    // Save deck to decks table.
    $deck_id = save_deck($db, $title, $description, $commander_id, $archetype, $user_id);

    // Save deck list to deck_cards table.
    // save_deck_cards($db, $deck_id, $decklist);
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
                    <!-- TODO: Add partner commander option. -->
                    <label for="title">Commander:</label>
                    <input name="commander" id="commander">
                </p>
                <p>
                    <label for="description">Description:</label>
                    <textarea name="description" id="description"></textarea>
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