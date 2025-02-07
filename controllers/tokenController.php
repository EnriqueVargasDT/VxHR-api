<?php
require_once '../models/token.php';

class TokenController {
    private $token;

    public function __construct() {
        $this->token = new Token();
    }

    public function validate() {
        return $this->token->validate();
    }
}
?>