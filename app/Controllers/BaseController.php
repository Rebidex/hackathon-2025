<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

abstract class BaseController
{
    public function __construct(
        protected Twig $view,
    ) {}

    protected function render(Response $response, string $template, array $data = []): Response
    {
        // Removed CSRF token from data, not working properly
        // $csrf = $this->view->getEnvironment()->getGlobals()['csrf'] ?? [];
        // $data['csrf'] = $csrf;

        //a flash message to the data if they exist
        if(isset($_SESSION['flash_messages']))
        {
            $data['flash_messages'] = $_SESSION['flash_messages'];
            unset($_SESSION['flash_messages']);
        }

        return $this->view->render($response, $template, $data);
    }

    protected function addFlashMessage(string $type, string $message): void
    {
        if(!isset($_SESSION['flash_messages']))
        {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    // TODO: add here any common controller logic and use in concrete controllers
}
