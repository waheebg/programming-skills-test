<?php

namespace App\Controllers;

abstract class Controller
{
    /**
     * Render a view file with extracted data.
     *
     * @param string $view Relative view path without .php extension (e.g. 'home/index' or 'errors/404')
     * @param array $data Associative array of variables to pass to the view
     */
    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $view) . '.php';

        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            http_response_code(500);
            echo "View not found.";
        }
    }

    /**
     * Return JSON response with appropriate headers.
     *
     * @param mixed $data
     * @param int $statusCode
     */
    protected function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }

    /**
     * Redirect to another URL or route path.
     *
     * @param string $path
     */
    protected function redirect(string $path): void
    {
        if (!preg_match('#^https?://#i', $path)) {
            $path = url($path);
        }

        header('Location: ' . $path);
        exit;
    }
}
