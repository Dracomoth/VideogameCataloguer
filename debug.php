<?php
require_once __DIR__ . '/db.php';
$cols =$pdo->query("DESCRIBE `Games`")->fetchAll(PDO::FETCH_COLUMN);
echo '<pre>'; print_r($cols); echo '</pre>';