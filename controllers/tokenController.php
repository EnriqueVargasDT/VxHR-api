<?php
require_once '../models/token.php';

class TokenController {
    public function validate() {
        $token = new Token();
        $result = $token->validate();
        return $result;
    }
}
?>