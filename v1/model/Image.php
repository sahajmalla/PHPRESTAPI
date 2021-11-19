<?php

class ImageException extends Exception
{
}

class Image
{
    private $_id;
    private $_title;
    private $_filename;
    private $_mimetype;
    private $_postid;
    private $_uploadFolderLocation;


    public function __construct(
        $id,
        $title,
        $filename,
        $mimetype,
        $postid
    ) {
        $this->setID($id);
        $this->setTitle($title);
        $this->setFilename($filename);
        $this->setMimetype($mimetype);
        $this->setPostID($postid);

        $this->_uploadFolderLocation = "../../postImages/";
    }

    public function getID()
    {
        return $this->_id;
    }

    public function getTitle()
    {
        return $this->_title;
    }

    public function getFilename()
    {
        return $this->_filename;
    }

    public function getFileExtension()
    {
        $filenameParts = explode(".", $this->_filename);


        if (!$filenameParts) {
            throw new ImageException("Filename does not contain a file extension");
        }

        $lastArrayElement = count($filenameParts) - 1;

        $fileExtension = $filenameParts[$lastArrayElement];

        return $fileExtension;
    }

    public function getMimetype()
    {
        return $this->_mimetype;
    }

    public function getPostID()
    {
        return $this->_postid;
    }

    public function getUploadFolderLocation()
    {
        return $this->_uploadFolderLocation;
    }

    public function setID($id)
    {

        if (
            ($id !== null) &&
            (!is_numeric($id) ||
                $id <= 0 ||
                $id > 9223372036854775807 ||
                $this->_id !== null)
        ) {
            throw new ImageException("Image ID error");
        }
        $this->_id = $id;
    }

    public function setTitle($title)
    {

        if (strlen($title) < 1 || strlen($title) > 255) {
            throw new ImageException("Image title error");
        }
        $this->_title = $title;
    }

    public function setFilename($filename)
    {

        if (
            strlen($filename) < 1 ||
            strlen($filename) > 30 ||
            preg_match("/^[a-zA-Z0-9_-]+(.jpg|.gif|.png)$/", $filename) != 1
        ) {
            throw new ImageException("Image filename error - must be between 1 and 30 characters long and only contain alphanumeric, underscore, hyphen, no spaces and have a .jpg, .gif or a .png file extension");
        }
        $this->_filename = $filename;
    }

    public function setMimetype($mimetype)
    {
        if (strlen($mimetype) < 1 || strlen($mimetype) > 255) {
            throw new ImageException("Image mimetype error");
        }
        $this->_mimetype = $mimetype;
    }

    public function setPostID($postid)
    {

        if (
            ($postid !== null) &&
            (!is_numeric($postid) ||
                $postid <= 0 ||
                $postid > 9223372036854775807 ||
                $this->_postid !== null)
        ) {
            throw new ImageException("Image Post ID error");
        }
        $this->_postid = $postid;
    }

    public function getImageURL()
    {

        $httpOrHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");

        $host = $_SERVER['HTTP_HOST'];

        $url = "PHP-REST-API/v1/posts/" . $this->getPostID() . "/images/" . $this->getID();

        return $httpOrHttps . "://" . $host . $url;
    }

    public function returnImageFile()
    {
        $filepath = $this->getUploadFolderLocation() . $this->getPostID() . '/' . $this->getFilename();

        if (!file_exists($filepath)) {
            throw new ImageException("Image file not found");
        }

        header('Content-Type: ' . $this->getMimetype());

        header('Content-Disposition: inline; filename="' . $this->getFilename() . '"');

        if (!readfile($filepath)) {
            http_response_code(404);
            exit;
        }
        exit;
    }

    public function saveImageFile($tempFileName)
    {
        $uploadedFilePath =  $this->getUploadFolderLocation() . $this->getPostID() . '/' . $this->getFilename();

        if (!is_dir($this->getUploadFolderLocation() . $this->getPostID())) {

            if (!mkdir($this->getUploadFolderLocation() . $this->getPostID())) {
                throw new ImageException("Failed to create image upload folder for post");
            }
        }
        if (!file_exists($tempFileName)) {
            throw new ImageException("Failed to upload image file");
        }
        if (!move_uploaded_file($tempFileName, $uploadedFilePath)) {
            throw new ImageException("Failed to upload image file");
        }
    }

    public function renameImageFile($oldFileName, $newFilename)
    {
        $originalFilePath = $this->getUploadFolderLocation() . $this->getPostID() . '/' . $oldFileName;
        $renamedFilePath = $this->getUploadFolderLocation() . $this->getPostID() . '/' . $newFilename;

        if (!file_exists($originalFilePath)) {
            throw new ImageException("Cannot find image file to rename");
        }

        if (!rename($originalFilePath, $renamedFilePath)) {
            throw new ImageException("Failed to update filename");
        }
    }

    public function deleteImageFile()
    {

        $filepath = $this->getUploadFolderLocation() . $this->getPostID() . '/' . $this->getFilename();

        if (file_exists($filepath)) {

            if (!unlink($filepath)) {
                throw new ImageException("Failed to delete image file");
            }
        }
    }

    public function returnImageAsArray()
    {
        $image = array();
        $image['id'] = $this->getID();
        $image['title'] = $this->getTitle();
        $image['filename'] = $this->getFilename();
        $image['mimetype'] = $this->getMimetype();
        $image['postid'] = $this->getPostID();
        $image['imageurl'] = $this->getImageURL();
        return $image;
    }
}
