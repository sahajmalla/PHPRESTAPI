<?php

class PostsException extends Exception
{
}

class Posts
{
    private
        $_id,
        $_slug,
        $_title,
        $_body,
        $_description,
        $_published,
        $_images;



    /**
     * __construct
     *
     * @param  mixed $id
     * @param  mixed $slug
     * @param  mixed $title
     * @param  mixed $body
     * @param  mixed $description
     * @param  mixed $published
     * @return void
     */
    public function __construct(
        $id,
        $slug,
        $title,
        $body,
        $description,
        $published,
        $images = []
    ) {
        $this->setID($id);
        $this->setSlug($slug);
        $this->setTitle($title);
        $this->setBody($body);
        $this->setDescription($description);
        $this->setPublished($published);
        $this->setImages($images);
    }


    /**
     * Gets the _id
     *
     * @return _id
     */
    public function getID()
    {
        return $this->_id;
    }

    /**
     * Gets the _slug
     *
     * @return _slug
     */
    public function getSlug()
    {
        return $this->_slug;
    }

    /**
     * Gets the _title
     *
     * @return _title
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * Gets the _body
     *
     * @return _body
     */
    public function getBody()
    {
        return $this->_body;
    }

    /**
     * Gets the _description
     *
     * @return _description
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * Gets the _published
     *
     * @return _published
     */
    public function getPublished()
    {
        return $this->_published;
    }

    public function getImages()
    {
        return $this->_images;
    }

    /**
     * Set the value of _id
     *
     */
    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new PostsException("Posts ID error");
        }

        $this->_id = $id;
    }

    /**
     * Set the value of _slug
     *
     */
    public function setSlug($slug)
    {
        if (strlen($slug) < 0 || strlen($slug) > 255) {
            throw new PostsException("Post slug error");
        }

        $this->_slug = $slug;
    }

    /**
     * Set the value of _title
     *
     */
    public function setTitle($title)
    {
        if (strlen($title) < 0 || strlen($title) > 255) {
            throw new PostsException("Post title error");
        }

        $this->_title = $title;
    }

    /**
     * Set the value of _body
     * 
     */
    public function setBody($body)
    {
        if (($body !== null) && (strlen($body) > 65535)) {
            throw new PostsException("Post body error");
        }

        $this->_body = $body;
    }

    /**
     * Set the value of _description
     *
     */
    public function setDescription($description)
    {
        if (($description !== null) && (strlen($description) > 65535)) {
            throw new PostsException("Post description error");
        }

        $this->_description = $description;
    }

    /**
     * Set the value of _published
     * 
     */
    public function setPublished($published)
    {
        if (strtoupper($published) !== 'Y' && strtoupper($published) !== 'N') {
            throw new PostsException("Post publish should be either Y or N ");
        }

        $this->_published = $published;
    }

    public function setImages($images)
    {

        if (!is_array($images)) {
            throw new TaskException("Post images is not an array");
        }
        $this->_images = $images;
    }

    /**
     * returnPostsAsArray
     *
     * @return $posts
     */
    public function returnPostsAsArray()
    {
        $posts = [];
        $posts['id'] = $this->getID();
        $posts['slug'] = $this->getSlug();
        $posts['title'] = $this->getTitle();
        $posts['body'] = $this->getBody();
        $posts['description'] = $this->getDescription();
        $posts['published'] = $this->getPublished();
        $posts['images'] = $this->getImages();


        return $posts;
    }
}
