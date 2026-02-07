<?php
/**
 * HomeController - Dashboard / landing page after login.
 */
class HomeController
{
    public function index(): void
    {
        $pageTitle   = 'Home';
        $contentView = APP_DIR . '/views/home.php';
        require APP_DIR . '/views/layout.php';
    }
}
