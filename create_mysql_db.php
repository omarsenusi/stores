<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', 'root');
$pdo->exec('CREATE DATABASE IF NOT EXISTS stores');
echo "MySQL Database created successfully.\n";
