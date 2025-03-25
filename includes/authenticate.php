 <?php
define('ADMIN_LOGIN','admin');
define('ADMIN_PASSWORD','password');

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])
  || ($_SERVER['PHP_AUTH_USER'] != ADMIN_LOGIN)
  || ($_SERVER['PHP_AUTH_PW'] != ADMIN_PASSWORD)) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Commander Deckbuilder"');
  exit("You have entered the wrong credentials. Prepare to be arrested.");
  }
?>