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
        return $this->view->render($response, $template, $data);
    }

    // TODO: add here any common controller logic and use in concrete controllers
}
