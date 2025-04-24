<?php
session_start();

$admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';

if (!$admin) {
    header("Location: index.php");
    exit;
}
// Connects to the database.
require './includes/connect.php';

$page_title = "Admin Control Panel";
$error_message = [];
$success_message = [];

$query = "SELECT * FROM archetypes ORDER BY archetype ASC";
$statement = $db -> prepare($query);
$statement -> execute(); 
$archetypes = $statement -> fetchAll();

// Create a new archetype.
if (isset($_POST['create-archetype'])) {
    // Sanitize archetype input to escape HTML entities and filter out dangerous characters.
    $archetype = trim(filter_input(INPUT_POST, 'archetype', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    if (strlen($archetype) > 0) {
        $archetype_query = "SELECT archetype_id FROM archetypes WHERE archetype = :archetype";
        $statement = $db -> prepare($archetype_query);
        $statement -> bindValue('archetype', $archetype);
        $statement -> execute();
    
        if ($statement -> fetch()) {
            $error_message[] = "Archetype already exists.";
        }
    } else {
        $error_message[] = "Invalid archetype";
    }
    
    if (empty($error_message)) {
        $query = "INSERT INTO archetypes (archetype) VALUES (:archetype)";
        $statement = $db -> prepare($query);
        $statement -> bindValue('archetype', $archetype);

        if ($statement -> execute()) {
            $success_message[] = "Archetype created";
        } else {
            $error_message[] = "Error creating archetype";
        }
    }
}

// Update an existing archetype
if (isset($_POST['update-archetype'])) {
    // Sanitize archetype input to escape HTML entities and filter out dangerous characters.
    $archetype_id = filter_input(INPUT_POST, "archetype-id", FILTER_SANITIZE_NUMBER_INT);
    $archetype = trim(filter_input(INPUT_POST, 'archetype', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    // Gather current archetype info to check for changes.
    $query = "SELECT * FROM archetypes WHERE archetype_id = :archetype_id";
    $statement = $db -> prepare($query);
    $statement -> bindValue(':archetype_id', $archetype_id);
    $statement -> execute();
    $current_archetype_data = $statement -> fetch();

    // Check if archetype is different and valid.
    if ($archetype !== $current_archetype_data['archetype'] && strlen($archetype) > 0) {
        
        // Check if archetype already exists.
        $archetype_query = "SELECT archetype_id FROM archetypes WHERE archetype = :archetype AND archetype_id != :archetype_id";
        $statement = $db -> prepare($archetype_query);
        $statement -> bindValue('archetype', $archetype);
        $statement -> bindValue('archetype_id', $archetype_id);
        $statement -> execute();

        if ($statement -> fetch()) {
            $error_message[] = "Archetype already exists.";
        }

        if (empty($error_message)) {
            $query = "UPDATE archetypes SET archetype = :archetype WHERE archetype_id = :archetype_id";
            $statement = $db -> prepare($query);
            $statement -> bindValue('archetype', $archetype);
            $statement -> bindValue('archetype_id', $archetype_id);

            if ($statement -> execute()) {
                $success_message[] = "Archetype updated";
            } else {
                $error_message[] = "Error updating archetype";
            }
        }
    } else {
        $error_message[] = "Invalid archetype";
    }
}

// Delete an archetype.
if (isset($_POST['delete-archetype'])) {
    $archetype_id = filter_input(INPUT_POST, "archetype-id", FILTER_SANITIZE_NUMBER_INT);

    if (!empty($archetype_id)) {
        $query = "DELETE FROM archetypes WHERE archetype_id = :archetype_id";
        $statement = $db -> prepare($query);
        $statement -> bindValue(':archetype_id', $archetype_id);

        if ($statement -> execute()) {
            $success_message[] = "Archetype deleted";
        } else {
            $error_message[] = "Error deleting archetype";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <?php include './includes/head.php'; ?>
    <body>
        <header>
        <h1>Admin Control Panel</h1>
        <?php include './includes/navbar.php'; ?>
        </header>
        <?php if (!empty($error_message)): ?>
            <div id="message">
                <?php foreach ($error_message as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <?php if (!empty($success_message)): ?>
            <div id="message">
                <?php foreach ($success_message as $message): ?>
                    <p><?= $message ?></p>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <h2>Archetype Control</h2>
        <h3>Create New Archetype</h3>
        <form action="admin_archetypes.php" method="post">
            <fieldset>
                <table>
                    <tr class="deck">
                        <td><label for="archetype">Archetype:</label><input type="text" id="archetype" name="archetype" required /></td>
                        <td><input type="submit" name="create-archetype" value="Create Archetype"></td>
                    </tr>
                </table>
            </fieldset>
        </form>
        <h3>Edit Existing Archetype</h3>
        <table>
            <tr class="deck">
                <th>Archetype</th>
                <th colspan="2">Actions</th>
            </tr>

        <?php foreach ($archetypes as $archetype): ?>
            <tr class="deck">
                <form action="admin_archetypes.php" method="post">
                    <input type="hidden" name="archetype-id" value="<?= $archetype['archetype_id'] ?>">
                    <td>
                        <input type="text" name="archetype" value="<?= htmlspecialchars($archetype['archetype']) ?>">
                    </td>
                    <td>
                        <input type="submit" name="update-archetype" value="Update">
                    </td>
                    <td>
                        <input type="submit" name="delete-archetype" value="Delete">
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </table>
        </form>
    </body>
    <?php include './includes/footer.php'; ?>
</html>