<?php

// Define base path
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

// Forward to public/index.php (MVC application entry point)
require_once BASE_PATH . '/public/index.php';
