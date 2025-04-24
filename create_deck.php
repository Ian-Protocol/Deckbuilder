<?php
session_start();
// If the user is not logged in, they cannot view this page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
} else {
    $user_id = $_SESSION['user_id'];
}

// Connects to the database.
require './includes/connect.php';

// Image resizing and uploading.
require './includes/images.php';

$page_title = "Commander Deckbuilder - Create Deck";
$error_message = [];

// Fetch archetypes
$query = "SELECT * FROM archetypes ORDER BY archetype ASC";
$statement = $db -> prepare($query);
$statement -> execute();
$archetypes = $statement -> fetchAll();

// Parses the deck list from the pasted or uploaded text.
function parse_decklist($decklist_raw) {
    $lines = explode("\n", $decklist_raw);
    $deck = [];

    // https://regex101.com/
    // Regex to match most card names with known special chars excluding brackets
    // which are output by some deckbuilders or MTG programs. Update if necessary.
    // May not match some "un" set names but those are illegal in EDH.
    // Don't currently check legality though, perhaps something to do in future.
    $card_pattern = '/^(?<quantity>\d+)[xX]?\s+(?<name>[a-zA-Z0-9 \/\'\.,:-]*)/';

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
        $query = "INSERT INTO decks (user_id, title, description, archetype_id, card_id) 
        VALUES (:user_id, :title, :description, :archetype_id, :card_id)";
        $statement = $db -> prepare($query);

        //  Bind values to the parameters
        $statement -> bindValue(':user_id', $user_id);
        $statement -> bindValue(':title', $title);
        $statement -> bindValue(':description', $description);
        $statement -> bindValue(':archetype_id', $archetype);
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
    } else {
        // TODO: Else: handle error better here.
        throw new Exception('Failed to fetch data from API.');
    }

    if (!empty($card_data['cards'])) {
        // The first match is bad and wrong!
        // So filter the API response for an exact match.
        // TODO: implement a back-up search from downloaded JSON from Scryfall.
        $card = null;

        foreach ($card_data['cards'] as $card_result) {
            if (strcasecmp(trim($card_result['name']), $card_name) === 0) {
                $card = $card_result;
                break;
            }
        }

        // Coalescing the nulls!
        $columns = [
            'name' => $card['name'] ?? $card_name,
            'type' => $card['type'] ?? '',
            'mana_cost' => $card['manaCost'] ?? 0,
            'text' => $card['text'] ?? '',
            'power' => $card['power'] ?? null,
            'toughness' => $card['toughness'] ?? null,
            'image_url' => $card['imageUrl'] ?? null,
            'cmc' => $card['cmc'] ?? 0
        ];

        // Create query.
        // Separates each key by its delimiter.
        $query = "INSERT INTO cards (" . implode(", ", array_keys($columns)) . ") 
            VALUES (:" . implode(", :", array_keys($columns)) . ")";

        $statement = $db -> prepare($query);

        // Binds each value.
        foreach ($columns as $key => $value) {
            $statement -> bindValue(":$key", $value);
        }

        $statement -> execute();
        $card_id = $db -> lastInsertId();

        // The API does not always return a color identity.
        // Again, I need to use the back-up JSON,
        // because this is VERY IMPORTANT DATA!
        $color_identity = $card['colorIdentity'] ?? [];
        // Insert color identity into cards_mana_types table.
        insert_card_colors($db, $card_id, $color_identity);

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

        if (!$card_id) {
            continue;
        }

        $query = "INSERT INTO deck_cards (deck_id, card_id, quantity) 
        VALUES (:deck_id, :card_id, :quantity)";

        $statement = $db -> prepare($query);
        $statement -> bindValue(':deck_id', $deck_id);
        $statement -> bindValue(':card_id', $card_id);
        $statement -> bindValue(':quantity', $quantity);
        $statement -> execute();
    }
}

if ($_POST) {
    //  Sanitize user input to escape HTML entities and filter out dangerous characters.
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $archetype = filter_input(INPUT_POST, 'archetype_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // $decklist_raw = filter_input(INPUT_POST, 'decklist', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    // Is this safe enough? I need to retain proper string values in order to search API by card name.
    // Retrieve the input while stripping HTML tags and retaining special characters
    $decklist_raw = filter_input(INPUT_POST, 'decklist', FILTER_DEFAULT);
    // Replace all other line endings with \n.
    $decklist_raw = str_replace(["\r\n", "\r"], "\n", $decklist_raw);
    // Keep characters found in magic card names incl ASCII chars.
    // Replaces the rest, essentially sanitizing it.
    // Testing the effect of not escaping the + char since "+2 Mace" did not show up in the database.
    // Confirmed it exists in the MTG API.
    $decklist_raw = preg_replace('/[^\p{L}\p{N}\s\-\'\/"(),:+]/u', '', $decklist_raw);
    $decklist_raw = trim($decklist_raw);

    $commander_name = filter_input(INPUT_POST, 'commander', FILTER_DEFAULT);
    $commander_name = str_replace(["\r\n", "\r"], "\n", $commander_name);
    $commander_name = preg_replace('/[^\p{L}\p{N}\s\-\'\/"(),:+]/u', '', $commander_name);
    $commander_name = trim($commander_name);


    $image_upload_detected = isset($_FILES['image']) && ($_FILES['image']['error'] === 0);
    $upload_error_detected = isset($_FILES['image']) && ($_FILES['image']['error'] > 0);

    // TODO: Add better error processing for the below checks.
    // $valid_ids = array_column($archetypes, 'archetype_id');
    // if (!in_array($archetype, $valid_ids)) {
    //     header("Location: error.php");
    //     exit;
    // }

    // If title or content has less than 1 character, display error page.
    if (strlen($title) < 1) {
        $error_message[] = "Title cannot be empty";
    }

    if (strlen($commander_name) < 1) {
        $error_message[] = "Commander name cannot be empty";
    }

    if (strlen($decklist_raw) < 1) {
        $error_message[] = "Decklist cannot be empty";
    }

    if (empty($error_message)) {
        // Find commander id.
        $commander_id = find_card($db, $commander_name);

        // Parse decklist
        $decklist = parse_decklist($decklist_raw);

        // Save deck to decks table.
        $deck_id = save_deck($db, $title, $description, $commander_id, $archetype, $user_id);

        // Upload image to uploads folder and images table.
        if ($image_upload_detected && !$upload_error_detected) {
            upload_image($db, $deck_id);
        }

        // Save deck list to deck_cards table.
        save_deck_cards($db, $deck_id, $decklist);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <header>
        <h1>Commander Deckbuilder - Create Deck</h1>
        <?php include './includes/navbar.php'; ?>
        </header>
        <?php if (!empty($error_message)): ?>
            <div id="message">
                <?php foreach ($error_message as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <div id="register">
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
                    <select name="archetype_id" id="archetype" required>
                        <option value="">-- Select Archetype --</option>
                        <?php foreach ($archetypes as $archetype): ?>
                            <option value="<?= htmlspecialchars($archetype['archetype_id']) ?>">
                                <?= htmlspecialchars(ucwords($archetype['archetype'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select> 
                </P>
                <p>
                    <label for="decklist">Deck List:</label>
                    <textarea name="decklist" id="decklist" placeholder="Paste deck list here."></textarea>
                </p>
                <p>
                    <input type="submit" value="Submit">
                </p>
            </fieldset>
        </form>
        </div>
        <?php include './includes/footer.php'; ?>
    </body>
</html>