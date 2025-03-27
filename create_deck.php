<?php
// TODO: Create better authentication.
// Authenticates user
require './includes/authenticate.php';

// Connects to the database.
require './includes/connect.php';

// Image resizing if necessary.
// require 'C:\xampp\htdocs\_Assignments\php-image-resize-master\lib\ImageResize.php';
// require 'C:\xampp\htdocs\_Assignments\php-image-resize-master\lib\ImageResizeException.php';
// use \Gumlet\ImageResize;

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
function save_deck($db, $title, $description, $commander_id, $image_path, $archetype, $user_id) {
        // Query & binding.
        //  Build the parameterized SQL query and bind to the sanitized values.
        // TODO: Create a dynamic version like in fetch_card.
        $query = "INSERT INTO decks (user_id, title, description, image_path, archetype, card_id) 
        VALUES (:user_id, :title, :description, :image_path, :archetype, :card_id)";
        $statement = $db -> prepare($query);

        //  Bind values to the parameters
        $statement -> bindValue(':user_id', $user_id);
        $statement -> bindValue(':title', $title);
        $statement -> bindValue(':description', $description);
        $statement -> bindValue(':image_path', $image_path);
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
    $query = "SELECT card_id FROM cards WHERE name = :card_name";
    $statement = $db->prepare($query);
    $statement -> bindValue(':card_name', $card_name);
    $statement -> execute();
    $result = $statement->fetch();

    if ($result) {
        return $result['card_id'];
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

        // Insert color identity into cards_mana_types table.
        insert_card_colors($db, $card_id, $card['colorIdentity']);

        return $card_id;
    }
    // TODO: Else: handle error here.
    // return null?
}

// Insert card colors into database.
function insert_card_colors($db, $card_id, $color_identity) {
    foreach ($color_identity as $color_code) {
        // Acquire mana type ID from database.
        $select_id_query = "SELECT mana_id FROM mana_types WHERE mana_type = :color_code";

        $statement = $db -> prepare($select_id_query);
        $statement -> bindValue(':color_code', $color_code);
        $statement -> execute();
        $result = $statement -> fetch();
        $mana_id = $result['mana_id'];

        $insert_query = "INSERT INTO cards_mana_types (card_id, mana_id)
        VALUES (:card_id, :mana_id)";

        $statement = $db -> prepare($insert_query);
        $statement -> bindValue(':card_id', $card_id);
        $statement -> bindValue(':mana_id', $mana_id);
        $statement -> execute();
    }
}

// Saves deck's cards into deck_cards table.
function save_deck_cards($db, $deck_id, $decklist) {
    foreach ($decklist as $card_name => $quantity) {
        // Find the card ID. If it doesn't exist, exist it.
        $card_id = find_card($db, $card_name);

        $query = "INSERT INTO deck_cards (deck_id, card_id, quantity) 
        VALUES (:deck_id, :card_id, :quantity)";

        $statement = $db -> prepare($query);
        $statement -> bindValue(':deck_id', $deck_id);
        $statement -> bindValue(':card_id', $card_id);
        $statement -> bindValue(':quantity', $quantity);
        $statement -> execute();
    }
}

function file_upload_path($original_filename, $upload_subfolder_name = 'uploads') {
    $current_folder = dirname(__FILE__);
    
    // Build an array of paths segment names to be joins using OS specific slashes.
    $path_segments = [$current_folder, $upload_subfolder_name, basename($original_filename)];
    
    // The DIRECTORY_SEPARATOR constant is OS agnostic.
    return join(DIRECTORY_SEPARATOR, $path_segments);
}

 // Checks if the file is a valid image or PDF.
function file_is_valid($temporary_path, $new_path) {
    $allowed_mime_types      = ['image/gif', 'image/jpeg', 'image/png'];
    $allowed_file_extensions = ['gif', 'jpg', 'jpeg', 'png'];
     
    $actual_file_extension   = pathinfo($new_path, PATHINFO_EXTENSION);
    $actual_mime_type        = mime_content_type($temporary_path);
     
    $file_extension_is_valid = in_array($actual_file_extension, $allowed_file_extensions);
    $mime_type_is_valid      = in_array($actual_mime_type, $allowed_mime_types);
     
    return $file_extension_is_valid && $mime_type_is_valid;
}

function upload_image() {
    $image_filename        = $_FILES['image']['name'];
    $temporary_image_path  = $_FILES['image']['tmp_name'];
    $new_image_path        = file_upload_path($image_filename);

    if (file_is_valid($temporary_image_path, $new_image_path)) {
        move_uploaded_file($temporary_image_path, $new_image_path);
        return $new_image_path;
    }
}

if ($_POST) {
    //  Sanitize user input to escape HTML entities and filter out dangerous characters.
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $commander_name = filter_input(INPUT_POST, 'commander', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $archetype = filter_input(INPUT_POST, 'archetype', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $decklist_raw = filter_input(INPUT_POST, 'decklist', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $image_upload_detected = isset($_FILES['image']) && ($_FILES['image']['error'] === 0);
    $upload_error_detected = isset($_FILES['image']) && ($_FILES['image']['error'] > 0);

    // TODO: Add better error processing for the below checks.
    if (!in_array($archetype, $archetypes)) {
        header("Location: error.php");
        exit;
    }

    // If title or content has less than 1 character, display error page.
    if (strlen($title) < 1 || strlen($commander_name) < 1) {
        header("Location: error.php");
        exit;
    }

    if (empty($decklist_raw)) {
        header("Location: error.php");
        exit;
    }

    if ($image_upload_detected && !$upload_error_detected) {
        $image_path = upload_image();
    } else {
        header("Location: error.php");
        exit;
    }

    // Find commander id.
    $commander_id = find_card($db, $commander_name);

    // Parse decklist
    $decklist = parse_decklist($decklist_raw);

    // Save deck to decks table.
    $deck_id = save_deck($db, $title, $description, $commander_id, $image_path, $archetype, $user_id);

    // Save deck list to deck_cards table.
    save_deck_cards($db, $deck_id, $decklist);
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