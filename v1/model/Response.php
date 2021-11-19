<?php

/**
 * Response class returns items or data that we want to return to the user
 */
class Response
{
    private
        $_success,
        $_httpStatusCode,
        $_messages = [],
        $_data,
        $_toCache = false,
        $_responseData = [];

    /**
     * Sets the success variable if its true or false
     *
     * @param  mixed $success
     * @return void
     */
    public function setSuccess($success)
    {
        $this->_success = $success;
    }

    /**
     * Sets the Http Status Code
     *
     * @param  mixed $httpStatusCode
     * @return void
     */
    public function setHttpStatusCode($httpStatusCode)
    {
        $this->_httpStatusCode = $httpStatusCode;
    }

    /**
     * Adds message to message array
     *
     * @param  mixed $message
     * @return void
     */
    public function addMessage($message)
    {
        $this->_messages[] = $message;
    }

    /**
     * Sets data
     *
     * @param  mixed $data
     * @return void
     */
    public function setData($data)
    {
        $this->_data = $data;
    }

    /**
     * Determines to cache or not
     *
     * @param  mixed $toCache
     * @return void
     */
    public function toCache($toCache)
    {
        $this->_toCache =  $toCache;
    }

    /**
     * Sends response to the client 
     *
     * @return void
     */
    public function send()
    {
        header(
            'Content-type: application/json;'
        );

        if ($this->_toCache) {
            header(
                'Cache-control: max-age=60'
            );
        } else {
            header(
                'Cache-control: no-cache, no-store'
            );
        }

        if (($this->_success !== false && $this->_success !== true) || !is_numeric($this->_httpStatusCode)) {
            http_response_code(500);
            $this->_responseData['statusCode'] = 500;
            $this->_responseData['success'] = false;
            $this->addMessage("Response creation error");
            $this->_responseData['messages'] = $this->_messages;
        } else {
            http_response_code($this->_httpStatusCode);
            $this->_responseData['statusCode'] = $this->_httpStatusCode;
            $this->_responseData['success'] =  $this->_success;
            $this->_responseData['messages'] = $this->_messages;
            $this->_responseData['data'] = $this->_data;
        }

        echo json_encode($this->_responseData);
    }
}
