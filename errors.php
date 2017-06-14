<?php

/**
    TODO(andrew): Create better error classes and error messages, especially for
    HTTP Response errors. They work as-is right now, but could be better.
*/

class HacksterError {
    public $message;
}

class HTTPResponseError extends HacksterError { // used for 4xx and 5xx errors.
    public $http_code;

    function __construct($http_code) {
        $this->http_code = $http_code;
        $this->message = "There was an error communicating with Hackster. The HTTP request returned an error of " . $this->http_code;
    }
}

class CurlExecError extends HacksterError { // used when curl_exec returns false
    // need the curl_error, just have that passed in
    public $curl_error;

    function __construct($curl_error) {
        $this->curl_error = $curl_error;
        $this->message = "curl_exec() failed with error " . $this->curl_error;
    }
}

class ProductIDMissingError extends HacksterError { // very specific, means that no product matching the given name was found
    public $product_name;

    function __construct($product_name) {
        $this->product_name = trim($product_name);
        $this->message = "No product was found that matched " . $this->product_name . ". Are you sure this is the correct name?";
    }
}

class NoProjectsFoundError extends HacksterError { // no projects related to the product were found. Link to the product page and encourage people to post
    public $product_link;
    public $product_name;

    function __construct($product_name) {
        $this->product_name = str_replace("\"", "", trim($product_name));
        $this->product_link = "https://www.hackster.io/digilent/products/" . strtolower(str_replace(array(" ", "\""), array("-", ""), trim($product_name)));
        $this->message = "No projects were found for the " . $this->product_name . ". Be the first to post a project <a href=" . $this->product_link . ">here!</a>";
    }
}
