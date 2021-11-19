<?php

require_once('DB.php');
require_once('../model/Posts.php');
require_once('../model/Response.php');
require_once('../model/Image.php');


function retrievePostImages($dbConn, $postid)
{
    $imageQuery = $dbConn->prepare(
        'SELECT 
        images.id, 
        images.title,
        images.filename, 
        images.mimetype, 
        images.postid 
        from 
        images, posts 
        where 
        posts.id = :postid 
        and 
        images.postid = posts.id'
    );
    $imageQuery->bindParam(':postid', $postid, PDO::PARAM_INT);
    $imageQuery->execute();

    $imageArray = array();

    while ($imageRow = $imageQuery->fetch(PDO::FETCH_ASSOC)) {

        $image = new Image(
            $imageRow['id'],
            $imageRow['title'],
            $imageRow['filename'],
            $imageRow['mimetype'],
            $imageRow['postid']
        );

        $imageArray[] = $image->returnImageAsArray();
    }

    return $imageArray;
}

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $e) {
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit();
}


if (array_key_exists("postid", $_GET)) {
    $postid = $_GET['postid'];

    if ($postid = '' || !is_numeric($postid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Post id cannot be blank. Post id must be numeric");
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare('SELECT * FROM posts WHERE id = :postid');
            $query->bindParam(':postid', $_GET['postid'], PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Post not found");
                $response->send();
                exit;
            }

            $postArray = [];

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                $imageArray = retrievePostImages($readDB, $row['id']);

                $post = new Posts(
                    $row['id'],
                    $row['slug'],
                    $row['title'],
                    $row['body'],
                    $row['description'],
                    $row['published'],
                    $imageArray
                );
                $postArray[] = $post->returnPostsAsArray();
            }

            $returnData = [];
            $returnData['rows_returned'] = $rowCount;
            $returnData['posts'] = $postArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PostsException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        } catch (PDOException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get Posts");
            $response->send();
            exit();
        } catch (ImageException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

        // begin authorization script
        if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {

            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
            (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
            $response->send();
            exit;
        }

        $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];


        try {
            $query = $writeDB->prepare('SELECT userid, accesstokenexpiry FROM sessions WHERE accesstoken = :accesstoken');
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("Access token provided is incorrect.");
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_userid = $row['userid'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];

            if (strtotime($returned_accesstokenexpiry) < time()) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("Access token has expired. Please login again.");
                $response->send();
                exit;
            }
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was an issue authenticating. Please try again.");
            $response->send();
            exit;
        }


        // end authorization script

        try {

            $query = $writeDB->prepare('DELETE FROM posts WHERE id =:postid AND userid = :userid');
            $query->bindParam(':postid', $_GET['postid'], PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Post not found");
                $response->send();
                exit;
            }

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Post deleted");
            $response->send();
            exit;
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to delete Post");
            $response->send();
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

        // begin authorization script
        if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {

            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
            (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
            $response->send();
            exit;
        }

        $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];


        try {
            $query = $writeDB->prepare('SELECT userid, accesstokenexpiry FROM sessions WHERE accesstoken = :accesstoken');
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("Access token provided is incorrect.");
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_userid = $row['userid'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];

            if (strtotime($returned_accesstokenexpiry) < time()) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("Access token has expired. Please login again.");
                $response->send();
                exit;
            }
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was an issue authenticating. Please try again.");
            $response->send();
            exit;
        }


        // end authorization script

        try {

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {

                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content type header is not set to JSON");
                $response->send();
                exit;
            }

            $rawPatchData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPatchData)) {

                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }

            $slug_updated = false;
            $title_updated = false;
            $body_updated = false;
            $description_updated = false;
            $published_updated = false;

            $queryFields = "";

            if (isset($jsonData->slug)) {
                $slug_updated = true;
                $queryFields .= "slug = :slug, ";
            }
            if (isset($jsonData->title)) {
                $title_updated = true;
                $queryFields .= "title = :title, ";
            }
            if (isset($jsonData->body)) {
                $body_updated =  true;
                $queryFields .= "body = :body, ";
            }
            if (isset($jsonData->description)) {
                $description_updated = true;
                $queryFields .= "description = :description, ";
            }
            if (isset($jsonData->published)) {
                $published_updated = true;
                $queryFields .= "published = :published, ";
            }


            $queryFields = rtrim($queryFields, ", ");


            if (
                $slug_updated === false &&
                $title_updated  === false &&
                $body_updated === false &&
                $description_updated === false &&
                $published_updated === false
            ) {

                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No post field provided");
                $response->send();
                exit;
            }

            $query =  $writeDB->prepare(
                'SELECT * FROM posts where id = :postid AND userid =:userid'
            );

            $query->bindParam(':postid', $_GET['postid'], PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Post not found to update");
                $response->send();
                exit;
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $post = new Posts(
                    $row['id'],
                    $row['slug'],
                    $row['title'],
                    $row['body'],
                    $row['description'],
                    $row['published']
                );
            }

            $queryString = "UPDATE posts SET " . $queryFields . " WHERE id = :postid AND userid =:userid";
            $query =  $writeDB->prepare($queryString);

            if ($slug_updated === true) {
                $post->setSlug($jsonData->slug);
                $up_slug = $post->getSlug();
                $query->bindParam(':slug', $up_slug, PDO::PARAM_STR);
            }

            if ($title_updated === true) {
                $post->setTitle($jsonData->title);
                $up_title = $post->getTitle();
                $query->bindParam(':title', $up_title, PDO::PARAM_STR);
            }

            if ($body_updated === true) {
                $post->setBody($jsonData->body);
                $up_body = $post->getBody();
                $query->bindParam(':body', $up_body, PDO::PARAM_STR);
            }

            if ($description_updated === true) {
                $post->setDescription($jsonData->description);
                $up_description = $post->getDescription();
                $query->bindParam(':description', $up_description, PDO::PARAM_STR);
            }

            if ($published_updated === true) {
                $post->setPublished($jsonData->published);
                $up_published = $post->getPublished();
                $query->bindParam(':published', $up_published, PDO::PARAM_STR);
            }
            $query->bindParam(':postid', $_GET['postid'], PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Post not updated");
                $response->send();
                exit;
            }

            $query =  $writeDB->prepare(
                'SELECT * FROM posts where id = :postid AND userid =:userid'
            );

            $query->bindParam(':postid', $_GET['postid'], PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No post found after update");
                $response->send();
                exit;
            }

            $postArray = [];

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                $imageArray = retrievePostImages($writeDB, $row['id']);
                $post = new Posts(
                    $row['id'],
                    $row['slug'],
                    $row['title'],
                    $row['body'],
                    $row['description'],
                    $row['published'],
                    $imageArray
                );
                $postArray[] = $post->returnPostsAsArray();
            }

            $returnData = [];
            $returnData['rows_returned'] = $rowCount;
            $returnData['posts'] = $postArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Post Updated");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PostsException $e) {

            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        } catch (PDOException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to update posts into database -  check sumbitted data for errors");
            $response->send();
            exit;
        } catch (ImageException $e) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} elseif (array_key_exists("published", $_GET)) {

    $published = $_GET['published'];

    if ($published !== 'Y' && $published !== 'N') {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Published must be Y or N");
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {

            $query =  $readDB->prepare('SELECT * FROM posts WHERE published = :published');
            $query->bindParam(':published', $_GET['published'], PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            $postArray = [];

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrievePostImages($readDB, $row['id']);
                $post = new Posts(
                    $row['id'],
                    $row['slug'],
                    $row['title'],
                    $row['body'],
                    $row['description'],
                    $row['published'],
                    $imageArray
                );
                $postArray[] = $post->returnPostsAsArray();
            }

            $returnData = [];
            $returnData['rows_returned'] = $rowCount;
            $returnData['posts'] = $postArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PostsException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        } catch (PDOException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get Posts");
            $response->send();
            exit;
        } catch (ImageException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Invalid Request Method");
        $response->send();
        exit;
    }
} elseif (array_key_exists("page", $_GET)) {


    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $page = $_GET['page'];

        if ($page == '' || !is_numeric($page)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Page number cannot be blank and must be numeric");
            $response->send();
            exit;
        }

        $limitPerPage = 2;

        try {

            $query =  $readDB->prepare('SELECT count(id) as totalNoOfPosts FROM posts where published = "Y"');
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $postsCount = intval($row['totalNoOfPosts']);

            $numOfPages = ceil($postsCount / $limitPerPage);

            if ($numOfPages == 0) {
                $numOfPages = 1;
            }

            if ($page > $numOfPages || $page == 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Page not found");
                $response->send();
                exit;
            }

            $offset = ($page == 1 ?  0 : ($limitPerPage * ($page - 1)));

            $query = $readDB->prepare('SELECT * FROM POSTS where published = "Y" LIMIT :pglimit OFFSET :offset');
            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            $postArray = [];


            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrievePostImages($readDB, $row['id']);
                $post = new Posts(
                    $row['id'],
                    $row['slug'],
                    $row['title'],
                    $row['body'],
                    $row['description'],
                    $row['published'],
                    $imageArray
                );
                $postArray[] = $post->returnPostsAsArray();
            }

            $returnData = [];
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $postsCount;
            $returnData['total_pages'] = $numOfPages;
            ($page < $numOfPages ? $returnData['has_next_page'] = true :  $returnData['has_next_page'] = false);
            ($page > 1 ? $returnData['has_previous_page'] = true :  $returnData['has_previous_page'] = false);
            $returnData['posts'] = $postArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PostsException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        } catch (PDOException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get Posts");
            $response->send();
            exit;
        } catch (ImageException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request Method is not allowed");
        $response->send();
        exit;
    }
} elseif (empty($_GET)) {


    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {

            $query = $readDB->prepare('SELECT * from posts');
            $query->execute();

            $rowCount = $query->rowCount();

            $postArray = [];

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrievePostImages($readDB, $row['id']);
                $post = new Posts(
                    $row['id'],
                    $row['slug'],
                    $row['title'],
                    $row['body'],
                    $row['description'],
                    $row['published'],
                    $imageArray
                );
                $postArray[] = $post->returnPostsAsArray();
            }

            $returnData = [];
            $returnData['rows_returned'] = $rowCount;
            $returnData['posts'] = $postArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PostsException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        } catch (PDOException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get posts");
            $response->send();
            exit;
        } catch (ImageException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {


        // begin authorization script
        if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {

            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
            (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
            $response->send();
            exit;
        }

        $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];


        try {
            $query = $writeDB->prepare('SELECT userid, accesstokenexpiry FROM sessions WHERE accesstoken = :accesstoken');
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("Access token provided is incorrect.");
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_userid = $row['userid'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];

            if (strtotime($returned_accesstokenexpiry) < time()) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("Access token has expired. Please login again.");
                $response->send();
                exit;
            }
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was an issue authenticating. Please try again.");
            $response->send();
            exit;
        }


        // end authorization script

        try {

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {

                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content type header is not set to JSON");
                $response->send();
                exit;
            }

            $rawPOSTData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPOSTData)) {

                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }

            if (
                !isset($jsonData->title) || !isset($jsonData->slug) ||
                !isset($jsonData->body) || !isset($jsonData->description) || !isset($jsonData->published)
            ) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);

                if (!isset($jsonData->title)) {
                    $response->addMessage('Title field is mandatory and must be provided');
                }
                if (!isset($jsonData->slug)) {
                    $response->addMessage('Slug field is mandatory and must be provided');
                }
                if (!isset($jsonData->body)) {
                    $response->addMessage('Body field is mandatory and must be provided');
                }
                if (!isset($jsonData->description)) {
                    $response->addMessage('Description field is mandatory and must be provided');
                }
                if (!isset($jsonData->published)) {
                    $response->addMessage('Published field is mandatory and must be provided');
                }

                $response->send();
                exit;
            }

            $newPost = new Posts(
                null,
                $jsonData->slug,
                $jsonData->title,
                $jsonData->body,
                $jsonData->description,
                $jsonData->published
            );

            $slug =  $newPost->getSlug();
            $title =  $newPost->getTitle();
            $body =  $newPost->getBody();
            $description =  $newPost->getDescription();
            $published =  $newPost->getPublished();

            $query = $writeDB->prepare(
                'INSERT INTO posts (slug, title, body, description, published, userid) 
                VALUES (:slug, :title, :body, :description, :published, :userid)  '
            );

            $query->bindParam(':slug', $slug, PDO::PARAM_STR);
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':body', $body, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':published', $published, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);

            $query->execute();

            $rowcount =  $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to create Post");
                $response->send();
                exit;
            }

            $lastPostID = $writeDB->lastInsertId();

            $query = $writeDB->prepare('SELECT * FROM posts where id = :postid and userid = :userid');

            $query->bindParam(':postid', $lastPostID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);

            $query->execute();

            $rowcount =  $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to retrieve Post after insertion");
                $response->send();
                exit;
            }

            $postArray = [];

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $post = new Posts($row['id'], $row['slug'], $row['title'], $row['body'], $row['description'], $row['published']);
                $postArray[] = $post->returnPostsAsArray();
            }

            $returnData = [];
            $returnData['rows_returned'] = $rowCount;
            $returnData['posts'] = $postArray;

            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PostsException $e) {

            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        } catch (PDOException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to insert posts into database -  check sumbitted data for errors");
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint not found");
    $response->send();
    exit;
}
