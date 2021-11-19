<?php

require_once('DB.php');
require_once('../model/Image.php');
require_once('../model/Response.php');

function sendResponse(
    $statusCode,
    $success,
    $message = null,
    $toCache = false,
    $data = null
) {

    $response = new Response();
    $response->setHttpStatusCode($statusCode);
    $response->setSuccess($success);

    if ($message != null) {
        $response->addMessage($message);
    }
    $response->toCache($toCache);

    if ($data != null) {
        $response->setData($data);
    }
    $response->send();
    exit;
}

function uploadImageRoute($readDB, $writeDB, $postid, $returned_userid)
{

    try {

        if (
            !isset($_SERVER['CONTENT_TYPE']) ||
            strpos($_SERVER['CONTENT_TYPE'], "multipart/form-data; boundary=") === false
        ) {

            sendResponse(400, false, "Content Type header not set to multipart/form-data with a boundary");
        }

        $query = $readDB->prepare('SELECT id from posts where id = :postid and userid = :userid');
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(404, false, "Post not found");
        }

        if (!isset($_POST['attributes'])) {

            sendResponse(400, false, "Attributes missing from body of request");
        }

        if (!$jsonImageAttributes = json_decode($_POST['attributes'])) {

            sendResponse(400, false, "Attributes field is not valid JSON");
        }

        if (
            !isset($jsonImageAttributes->title) ||
            !isset($jsonImageAttributes->filename) ||
            $jsonImageAttributes->title == '' ||
            $jsonImageAttributes->filename == ''
        ) {

            sendResponse(400, false, "Title and Filename fields are mandatory");
        }

        if (strpos($jsonImageAttributes->filename, ".") > 0) {

            sendResponse(400, false, "Filename must not contain a file extension");
        }

        if (!isset($_FILES['imagefile']) || $_FILES['imagefile']['error'] !== 0) {

            sendResponse(500, false, "Image file upload unsuccessful - make sure you have selected a file");
        }

        $imageFileDetails = getimagesize($_FILES['imagefile']["tmp_name"]);

        if ($imageFileDetails == false) {

            sendResponse(400, false, "Not a valid image file");
        }

        if (isset($_FILES['imagefile']['size']) && $_FILES['imagefile']['size'] > 5242880) {

            sendResponse(400, false, "File must be under 5MB");
        }

        $allowedImageFileTypes = array('image/jpeg', 'image/gif', 'image/png');

        if (!in_array($imageFileDetails['mime'], $allowedImageFileTypes)) {

            sendResponse(400, false, "File type not supported");
        }

        $fileExtension = "";

        switch ($imageFileDetails['mime']) {
            case "image/jpeg":
                $fileExtension = ".jpg";
                break;
            case "image/gif":
                $fileExtension = ".gif";
                break;
            case "image/png":
                $fileExtension = ".png";
                break;
            default:
                break;
        }

        if ($fileExtension == "") {
            sendResponse(400, false, "No valid file extension found from mimetype");
        }

        $image = new Image(
            null,
            $jsonImageAttributes->title,
            $jsonImageAttributes->filename . $fileExtension,
            $imageFileDetails['mime'],
            $postid
        );

        $title = $image->getTitle();
        $newFileName = $image->getFilename();
        $mimetype = $image->getMimetype();

        $query = $readDB->prepare(
            'SELECT images.id 
            from 
            images, posts 
            where images.postid = posts.id 
            and posts.id = :postid 
            and posts.userid = :userid 
            and images.filename = :filename'
        );
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->bindParam(':filename', $newFileName, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount !== 0) {

            sendResponse(409, false, "A file with that filename already exists for this post - try a different filename");
        }

        $query = $writeDB->prepare(
            'INSERT INTO images 
            (title, filename, mimetype, postid) 
            VALUES 
            (:title, :filename, :mimetype, :postid)'
        );
        $query->bindParam(':title', $title, PDO::PARAM_STR);
        $query->bindParam(':filename', $newFileName, PDO::PARAM_STR);
        $query->bindParam(':mimetype', $mimetype, PDO::PARAM_STR);
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(500, false, "Failed to upload image");
        }

        $lastImageID = $writeDB->lastInsertId();

        $query = $writeDB->prepare(
            'SELECT 
            images.id, 
            images.title, 
            images.filename, 
            images.mimetype, 
            images.postid 
            from 
            images, posts
             where
              images.id = :imageid 
              and 
              posts.id = :postid 
              and 
             posts.userid = :userid 
              and 
              images.postid = posts.id'
        );
        $query->bindParam(':imageid', $lastImageID, PDO::PARAM_INT);
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(500, false, "Failed to retrieve image attributes after upload - try uploading the image again");
        }

        $imageArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $image = new Image(
                $row['id'],
                $row['title'],
                $row['filename'],
                $row['mimetype'],
                $row['postid']
            );

            $imageArray[] = $image->returnImageAsArray();
        }
        $image->saveImageFile($_FILES['imagefile']['tmp_name']);

        sendResponse(201, true, "Image uploaded successfully", false, $imageArray);
    } catch (PDOException $e) {
        sendResponse(500, false, "Failed to upload image");
    } catch (ImageException $e) {
        sendResponse(500, false, $e->getMessage());
    }
}

function getImageAttributesRoute($readDB, $postid, $imageid)
{

    try {
        $query = $readDB->prepare(
            'SELECT 
            images.id, 
            images.title, 
            images.filename, 
            images.mimetype, 
            images.postid 
            from 
            images, posts 
            where 
            images.id = :imageid 
            and 
            posts.id = :postid
            and 
            images.postid = posts.id'
        );
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(404, false, "Image not found");
        }

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $image = new Image(
                $row['id'],
                $row['title'],
                $row['filename'],
                $row['mimetype'],
                $row['postid']
            );


            $imageArray[] = $image->returnImageAsArray();
            sendResponse(200, true, null, true, $imageArray);
        }
    } catch (ImageException $e) {
        sendResponse(500, false, $e->getMessage());
    } catch (PDOException $e) {
        sendResponse(500, false, "Failed to get image attributes");
    }
}

function getImageRoute($readDB, $postid, $imageid)
{

    try {
        $query = $readDB->prepare(
            'SELECT 
            images.id, 
            images.title, 
            images.filename, 
            images.mimetype, 
            images.postid 
            from 
            images, posts 
            where 
            images.id = :imageid 
            and
            posts.id = :postid 
            and 
            images.postid = posts.id'
        );
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(404, false, "Image not found");
        }
        $image = null;
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $image = new Image(
                $row['id'],
                $row['title'],
                $row['filename'],
                $row['mimetype'],
                $row['postid']
            );
        }

        if ($image == null) {

            sendResponse(404, false, "Image not found");
        }
        $image->returnImageFile();
    } catch (ImageException $e) {
        sendResponse(500, false, $e->getMessage());
    } catch (PDOException $e) {

        sendResponse(500, false, "Error getting image");
    }
}

function updateImageAttributesRoute($writeDB, $postid, $imageid, $returned_userid)
{
    try {
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {

            sendResponse(400, false, "Content Type header not set to JSON");
        }

        $rawPatchData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPatchData)) {

            sendResponse(400, false, "Request body is not valid JSON");
        }

        $title_updated = false;
        $filename_updated = false;

        $queryFields = "";

        if (isset($jsonData->title)) {

            $title_updated = true;

            $queryFields .= "images.title = :title, ";
        }

        if (isset($jsonData->filename)) {
            if (strpos($jsonData->filename, ".") !== false) {

                sendResponse(400, false, "Filename cannot contain any dots (or file extensions) - please remove the dot or file extension");
            }

            $filename_updated = true;

            $queryFields .= "images.filename = :filename, ";
        }

        $queryFields = rtrim($queryFields, ", ");

        if ($title_updated === false && $filename_updated === false) {

            sendResponse(400, false, "No image fields provided");
        }

        $query = $writeDB->prepare(
            'SELECT 
            images.id, 
            images.title, 
            images.filename, 
            images.mimetype, 
            images.postid 
            from 
            images, posts 
            where
            images.id = :imageid 
            and 
            images.postid = :postid
            and 
            images.postid = posts.id 
            and posts.userid = :userid'
        );
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(404, false, "No image found to update");
        }

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $image = new Image(
                $row['id'],
                $row['title'],
                $row['filename'],
                $row['mimetype'],
                $row['postid']
            );
        }

        $queryString = "update 
        images 
        inner join 
        posts
        on
        images.postid = posts.id 
        set " . $queryFields . " 
        where
        images.id = :imageid 
        and 
        images.postid = posts.id
        and
        images.postid = :postid
        and 
        posts.userid = :userid";

        $query = $writeDB->prepare($queryString);

        if ($title_updated === true) {

            $image->setTitle($jsonData->title);

            $up_title = $image->getTitle();

            $query->bindParam(':title', $up_title, PDO::PARAM_STR);
        }

        if ($filename_updated === true) {

            $originalFilename = $image->getFilename();

            $image->setFilename($jsonData->filename . "." . $image->getFileExtension());

            $up_filename = $image->getFilename();

            $query->bindParam(':filename', $up_filename, PDO::PARAM_STR);
        }

        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);

        $query->bindParam(':postid', $postid, PDO::PARAM_INT);

        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(400, false, "Image attributes not updated - given values may be the same as the stored values");
        }

        $query = $writeDB->prepare(
            'SELECT 
            images.id, 
            images.title, 
            images.filename,
            images.mimetype, 
            images.postid 
            from images, posts 
            where
            images.id = :imageid
            and 
            posts.id = :postid
            and
            posts.userid = :userid
            and
            images.postid = posts.id'
        );
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {


            sendResponse(404, false, "No image found");
        }

        $imageArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $image = new Image(
                $row['id'],
                $row['title'],
                $row['filename'],
                $row['mimetype'],
                $row['postid']
            );


            $imageArray[] = $image->returnImageAsArray();
        }

        if ($filename_updated === true) {

            $image->renameImageFile($originalFilename, $up_filename);
        }

        sendResponse(200, true, "Image attributes updated", false, $imageArray);
    } catch (PDOException $e) {
        sendResponse(500, false, "Failed to update image - check your data for errors");
    } catch (ImageException $e) {
        sendResponse(400, false, $e->getMessage());
    }
}

function deleteImageRoute($writeDB, $postid, $imageid, $returned_userid)
{

    try {

        $query = $writeDB->prepare(
            'SELECT
            images.id, 
            images.title,
            images.filename,
            images.mimetype,
            images.postid
            from images, posts 
            where 
            images.id = :imageid 
            and 
            posts.id = :postid 
            and 
            posts.userid = :userid
            and 
            images.postid = posts.id'
        );
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(404, false, "Image not found");
        }

        $image = null;

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $image = new Image(
                $row['id'],
                $row['title'],
                $row['filename'],
                $row['mimetype'],
                $row['postid']
            );
        }
        if ($image == null) {

            sendResponse(500, false, "Failed to get image");
        }

        $query = $writeDB->prepare(
            'DELETE 
            images
            from 
            images, posts 
            where 
            images.id = :imageid 
            and 
            posts.id = :postid 
            and 
            images.postid = posts.id 
            and 
            posts.userid = :userid'
        );
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':postid', $postid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {

            sendResponse(404, false, "Image not found");
        }

        $image->deleteImageFile();

        sendResponse(200, true, "Image deleted");
    } catch (PDOException $e) {

        sendResponse(500, false, $e->getMessage());
    } catch (ImageException $e) {

        sendResponse(500, false, $e->getMessage());
    }
}

function checkAuthStatusAndReturnUserID($writeDB)
{

    // begin authorization script
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {

        $message = null;
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $message = "Access token is missing from the header";
        } else {
            if (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
                $message = "Access token cannot be blank";
            }
        }

        sendResponse(401, false, $message);
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];


    try {
        $query = $writeDB->prepare('SELECT userid, accesstokenexpiry FROM sessions WHERE accesstoken = :accesstoken');
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(401, false, "Invalid access token");
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_userid = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];

        if (strtotime($returned_accesstokenexpiry) < time()) {
            sendResponse(401, false, "Access token has expired");
        }

        return $returned_userid;
    } catch (PDOException $e) {
        sendResponse(500, false, "There was an issue authenticating - please try again");
    }

    // end authorization script

}




try {

    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $e) {

    sendResponse(500, false, "Database connection error");
}

// /posts/1/images/5/attributs
if (
    array_key_exists("postid", $_GET)
    && array_key_exists("imageid", $_GET)
    && array_key_exists("attributes", $_GET)
) {
    $postid = $_GET['postid'];

    $imageid = $_GET['imageid'];

    $attributes = $_GET['attributes'];

    if ($imageid == '' || !is_numeric($imageid) || $postid == '' || !is_numeric($postid)) {

        sendResponse(400, false, "Image ID or Post ID cannot be blank or must be numeric");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        getImageAttributesRoute($readDB, $postid, $imageid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $returned_userid = checkAuthStatusAndReturnUserID($writeDB);

        updateImageAttributesRoute($writeDB, $postid, $imageid, $returned_userid);
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
    // /posts/1/images/5
} elseif (array_key_exists("postid", $_GET) && array_key_exists("imageid", $_GET)) {

    $postid = $_GET['postid'];

    $imageid = $_GET['imageid'];

    if ($imageid == '' || !is_numeric($imageid) || $postid == '' || !is_numeric($postid)) {

        // sendResponse(400, false, "Image ID or Post ID cannot be blank or must be numeric");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        getImageRoute($readDB, $postid, $imageid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $returned_userid = checkAuthStatusAndReturnUserID($writeDB);

        deleteImageRoute($writeDB, $postid, $imageid, $returned_userid);
    } else {

        sendResponse(405, false, "Request method not allowed");
    } // /posts/1/images/
} elseif (array_key_exists("postid", $_GET) && !array_key_exists("imageid", $_GET)) {

    $postid = $_GET['postid'];

    if ($postid == '' || !is_numeric($postid)) {
        // sendResponse(400, false, "Post ID cannot be blank or must be numeric");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $returned_userid = checkAuthStatusAndReturnUserID($writeDB);


        uploadImageRoute($readDB, $writeDB, $postid, $returned_userid);
    } else {

        sendResponse(405, false, "Request method not allowed");
    }
} else {

    sendResponse(404, false, "Endpoint not found");
}
