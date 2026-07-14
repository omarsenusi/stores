<?php
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'root', 'root');
$pdo->exec('CREATE DATABASE stores');
echo "Database created successfully.\n";
