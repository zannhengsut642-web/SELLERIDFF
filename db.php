<?php
function getDB() {
    // Mengembalikan objek tiruan agar tidak error di Vercel
    return new class {
        public function prepare($sql) {
            return new class {
                public function execute($args) {}
                public function fetch() { return ['state' => '', 'data' => '{}']; }
                public function fetchColumn() { return 0; }
            };
        }
        public function query($sql) {
            return new class {
                public function fetchAll($mode) { return []; }
                public function fetchColumn() { return 0; }
            };
        }
        public function exec($sql) {}
    };
}
