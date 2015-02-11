<?php
namespace Chetzof\Expector;

class UnexpectedFieldException extends \Exception {
    public function __construct($field){
        parent::__construct("Non-whitelisted field $field has not been defined");
    }
}