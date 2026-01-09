<?php
namespace App\Controllers;

/**
 * Base Controller
 */
abstract class Controller
{
    protected function view($path, $data = [])
    {
        extract($data);
        $viewFile = D_ROOT . '/resources/views/' . str_replace('.', '/', $path) . '.php';
        
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            die("View $path not found at $viewFile");
        }
    }
}

