<?php

require '../vendor/autoload.php';

$db = new PDO('mysql:host=localhost;dbname=simpledao;charset=utf8', 'root', ''); // nom de la base de données

$userDAO = new Tests\DAO\UserDAO($db);

$user = $userDAO->find(1);

$user->getCategory()->setName('Yop');

$userDAO->save($user);

var_dump($user);

