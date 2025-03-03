<?php
require_once '../models/catalog.php';

class CatalogController {
    private $catalog;

    public function __construct() {
        $this->catalog = new Catalog();
    }

    public function getAll($schema, $catalog, $available) {
        $this->catalog->getAll($schema, $catalog, $available);
    }

    public function getItemById($schema, $catalog, $id) {
        $this->catalog->getItemById($schema, $catalog, $id);
    }

    public function saveItem($schema, $catalog, $item) {
        $this->catalog->saveItem($schema, $catalog, $item);
    }

    public function updateItem($schema, $catalog, $item) {
        $this->catalog->updateItem($schema, $catalog, $item);
    }

    public function updateItemStatus($schema, $catalog, $item) {
        $this->catalog->updateItemStatus($schema, $catalog, $item);
    }
}
?>