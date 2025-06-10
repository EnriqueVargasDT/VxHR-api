<?php
require_once '../models/communication.php';

class CommunicationController {
    private $communication;

    public function __construct() {
        $this->communication = new Communication();
    }

    public function dashboard() {
        $this->communication->dashboard();
    }

    public function birthdays($all = null) {
        $this->communication->birthdays($all);
    }

    public function anniversaries($all = null) {
        $this->communication->anniversaries($all);
    }

    public function getAllPosts() {
        $this->communication->getAllPosts();
    }

     public function communication() {
        $this->communication->communication();
    }

    public function events() {
        $this->communication->events();
    }

    public function c4() {
        $this->communication->c4();
    }

    public function getAllPostTypes() {
        $this->communication->getAllPostTypes();
    }

    public function getPostById($id) {
        $this->communication->getPostById($id);
    }

    public function savePost($data) {
        $this->communication->savePost($data);
    }

    public function updatePost($id, $data) {
        $this->communication->updatePost($id, $data);
    }

    public function updatePostStatus($data) {
        $this->communication->updatePostStatus($data);
    }

    public function uploadPostFile($data) {
        $this->communication->uploadPostFile($data);
    }

    public function addBirthdayReaction($data) {
        $this->communication->addBirthdayReaction($data);
    }

    public function removeBirthdayReaction($data) {
        $this->communication->removeBirthdayReaction($data);
    }

    public function addBirthdayComment($data) {
        $this->communication->addBirthdayComment($data);
    }
}
?>