<?php
require_once '../models/catalog.php';

class CatalogController {
    private $catalog;

    public function __construct() {
        $this->catalog = new Catalog();
    }

    public function getRawData($schema, $catalog) {
        $this->catalog->getRawData($schema, $catalog);
    }

    public function getAll($schema, $catalog) {
        $this->catalog->getBasicData($schema, $catalog);
    }

    public function getItemDataById($schema, $catalog, $id) {
        $this->catalog->getItemDataById($schema, $catalog, $id);
    }

    public function saveNewItem($schema, $catalog, $item) {
        $this->catalog->saveNewItem($schema, $catalog, $item);
    }

    public function updateItem($schema, $catalog, $item) {
        $this->catalog->updateItem($schema, $catalog, $item);
    }

    public function updateStatus($schema, $catalog, $item) {
        $this->catalog->updateStatus($schema, $catalog, $item);
    }
}
?>