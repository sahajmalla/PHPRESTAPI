<?php

require_once('DB.php');
require_once('../model/Response.php');

try {
    $writeDB = DB::connectWriteDB();
} catch (PDOException $e) {
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method is not allowed");
    $response->send();
    exit;
}

if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Content type header should be set to application/json");
    $response->send();
    exit;
}

$rawPostData = file_get_contents('php://input');

if (!$jsonData = json_decode($rawPostData)) {

    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Request body is not valid JSON");
    $response->send();
    exit;
}

if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    if (!isset($jsonData->fullname)) {
        $response->addMessage("Full name is required");
    }
    if (!isset($jsonData->username)) {
        $response->addMessage("Username is required");
    }
    if (!isset($jsonData->password)) {
        $response->addMessage("Password is required");
    }
    $response->send();
    exit;
}

if (
    strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 ||
    strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 ||
    strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255
) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    if (strlen($jsonData->fullname) < 1) {
        $response->addMessage("Full name cannot be blank");
    }
    if (strlen($jsonData->fullname) > 255) {
        $response->addMessage("Full name cannot be more than 255 characters");
    }
    if (strlen($jsonData->username) < 1) {
        $response->addMessage("Username cannot be blank");
    }
    if (strlen($jsonData->username) > 255) {
        $response->addMessage("Username cannot be more than 255 characters");
    }
    if (strlen($jsonData->password) < 1) {
        $response->addMessage("Password cannot be blank");
    }
    if (strlen($jsonData->password) > 255) {
        $response->addMessage("Password cannot be more than 255 characters");
    }
    $response->send();
    exit;
}

$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;


try {
    $query = $writeDB->prepare('SELECT id from users where username = :username');
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();



    $rowCount = $query->rowCount();

    if ($rowCount !== 0) {
        $response = new Response();
        $response->setHttpStatusCode(409);
        $response->setSuccess(false);
        $response->addMessage("Username already exists");
        $response->send();
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = $writeDB->prepare(
        'INSERT INTO users
        (fullname, username, password)
        VALUES
        (:fullname, :username, :password)'
    );
    $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->bindParam(':password', $hashed_password, PDO::PARAM_STR);

    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Error in creating the user");
        $response->send();
        exit;
    }

    $lastUserID = $writeDB->lastInsertId();

    $returnData = [];
    $returnData['user_id'] =   $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    $response = new Response();
    $response->setHttpStatusCode(201);
    $response->setSuccess(true);
    $response->addMessage("User created");
    $response->setData($returnData);
    $response->send();
    exit;
} catch (PDOException $e) {
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Error on creating the user account");
    $response->send();
    exit;
}
