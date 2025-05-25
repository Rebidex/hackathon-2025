<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Domain\Service\MonthlySummaryService;
use App\Domain\Service\AlertGenerator;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly MonthlySummaryService $summaryService,
        private readonly AlertGenerator $alertGenerator,
    )
    {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int)$_SESSION['user_id'];
        $queryParameters = $request->getQueryParams();

        $year = (int)($queryParameters['year'] ?? date('Y'));
        $month = (int)($queryParameters['month'] ?? date('m'));

        $availableYears = $this->summaryService->getAvailableYears($userId);

        return $this->render($response, 'dashboard.twig', [
            'year' => $year,
            'month' => $month,
            'availableYears' => $availableYears,
            'totalForMonth'         => $this->summaryService->computeTotalExpenditure($userId, $year, $month),
            'totalsForCategories'   => $this->summaryService->computePerCategoryTotals($userId, $year, $month),
            'averagesForCategories' => $this->summaryService->computePerCategoryAverages($userId, $year, $month),
            'alerts' => $this->alertGenerator->generate($userId, $year, $month),
        ]);
    }
}
