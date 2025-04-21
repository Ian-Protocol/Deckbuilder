<?php
require 'C:\xampp\htdocs\_Assignments\php-image-resize-master\lib\ImageResize.php';
require 'C:\xampp\htdocs\_Assignments\php-image-resize-master\lib\ImageResizeException.php';
use \Gumlet\ImageResize;

// TODO: Do not replace if the image is not valid.

const REGULAR_WIDTH = 400;
const THUMBAIL_WIDTH = 50;
const UPLOAD_SUBFOLDER_NAME = 'uploads';

function file_upload_path($original_filename) {
    $current_folder = dirname(__FILE__);
    $parent_folder = dirname($current_folder);
    
    // Build an array of paths segment names to be joins using OS specific slashes.
    $path_segments = [$parent_folder, UPLOAD_SUBFOLDER_NAME, basename($original_filename)];
    
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

function upload_image($db, $deck_id) {
    $image_filename        = $_FILES['image']['name'];
    $temporary_image_path  = $_FILES['image']['tmp_name'];
    $new_image_path        = file_upload_path($image_filename);

    if (file_is_valid($temporary_image_path, $new_image_path)) {
        $image = new ImageResize($temporary_image_path);

        $regular_filename = substr_replace($image_filename, "_regular",
            strrpos($image_filename, ".")) . "." . pathinfo($image_filename, PATHINFO_EXTENSION);

        $thumbnail_filename = substr_replace($image_filename, "_thumbnail",
            strrpos($image_filename, ".")) . "." . pathinfo($image_filename, PATHINFO_EXTENSION);

        // Resize medium path
        $regular_path = substr_replace($new_image_path, $regular_filename, 
        strrpos($new_image_path, DIRECTORY_SEPARATOR) + 1);
        
        // Resize thumbnail path
        $thumbnail_path = substr_replace($new_image_path, $thumbnail_filename, 
        strrpos($new_image_path, DIRECTORY_SEPARATOR) + 1);

        // Resize and save image.
        $image
            ->resizeToWidth(REGULAR_WIDTH)
            ->save($regular_path)

            ->resizeToWidth(THUMBAIL_WIDTH)
            ->save($thumbnail_path)
        ;

        $image_query = "INSERT INTO images (deck_id, regular_path, thumbnail_path) 
        VALUES (:deck_id, :regular_path, :thumbnail_path)";

        $regular_filename = "." . DIRECTORY_SEPARATOR . UPLOAD_SUBFOLDER_NAME . DIRECTORY_SEPARATOR . $regular_filename;
        $thumbnail_filename = "." . DIRECTORY_SEPARATOR . UPLOAD_SUBFOLDER_NAME . DIRECTORY_SEPARATOR . $thumbnail_filename;

        $statement = $db -> prepare($image_query);
        $statement -> bindValue(':deck_id', $deck_id);
        $statement -> bindValue(':regular_path', $regular_filename);
        $statement -> bindValue(':thumbnail_path', $thumbnail_filename);
        $statement -> execute();
    }
}
?>