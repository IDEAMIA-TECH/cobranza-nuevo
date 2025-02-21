<?php
class Settings {
    private static $instance = null;
    private $settings = [];
    private $db;

    private function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->loadSettings();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadSettings() {
        $query = "SELECT setting_key, setting_value FROM system_settings";
        $stmt = $this->db->query($query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    public static function get($key, $default = '') {
        $instance = self::getInstance();
        return $instance->settings[$key] ?? $default;
    }

    public static function getAll() {
        $instance = self::getInstance();
        return $instance->settings;
    }
} 