<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\ExpenseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 20;
    private const DEFAULT_CATEGORIES = [
        'groceries', 'utilities',
        'transport', 'entertainment',
        'housing','health','other'
    ];
    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {

        $userId = (int)$_SESSION['user_id'];
        $queryParameters = $request->getQueryParams();

        $page = max(1,(int)($request->getQueryParams()['page'] ?? 1));
        $pageSize = max(1,(int)($request->getQueryParams()['pageSize'] ?? self::PAGE_SIZE));
        $year = (int)($queryParameters['year'] ?? date('Y'));
        $month = (int)($queryParameters['month'] ?? date('m'));

        $expenses = $this->expenseService->list($userId,$year,$month, $page, $pageSize);
        $totalExpanses = $this->expenseService->countForUser($userId, $year,$month);

        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $expenses,
            'page'     => $page,
            'pageSize' => $pageSize,
            'total'    => $totalExpanses,
            'year'     => $year,
            'month'    => $month,
            'availableYears'=>$this->expenseService->getAvailableYears($userId),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'expenses/create.twig',[
            'categories' => self::DEFAULT_CATEGORIES,
            'defaultDate' => date('Y-m-d'),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = (int)$_SESSION['user_id'];
        $formData = $request->getParsedBody();

        try{
            $this->expenseService->create(
                $userId,
                (float)$formData['amount'],
                $formData['description'],
                new \DateTimeImmutable($formData['date']),
                $formData['category'],
            );

            return $response
                ->withHeader('Location', '/expenses')
                ->withStatus(302);
        } catch(\InvalidArgumentException $e) {
            return $this->render($response,'expenses/create.twig', [
                'categories'=>self::DEFAULT_CATEGORIES,
                'errors'=>[$e->getMessage()],
                'formData'=>$formData,
            ]);
        }
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        $userId = (int)$_SESSION['user_id'];
        $expenseId = (int)$routeParams['id'];

        $expense = $this->expenseService->findById($expenseId);

        if(!$expense || $expense->userId !== $userId)
        {
            return $response->withStatus(403);
        }

        return $this->render($response, 'expenses/edit.twig', ['expense' => $expense, 'categories' => self::DEFAULT_CATEGORIES,]);
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        $userId = (int)$_SESSION['user_id'];
        $expenseId = (int)$routeParams['id'];
        $formData = $request->getParsedBody();
            try {
                $expense = $this->expenseService->findById($expenseId);

                if (!$expense || $expense->userId !== $userId) {
                    return $response->withStatus(403);
                }

                $this->expenseService->update(
                    $expense,
                    (float)$formData['amount'],
                    $formData['description'],
                    new \DateTimeImmutable($formData['date']),
                    $formData['category']
                );

                return $response
                    ->withHeader('Location', '/expenses')
                    ->withStatus(302);
            } catch (\InvalidArgumentException $e) {
                return $this->render($response, 'expenses/edit.twig', [
                    'expense' => $expense,
                    'categories' => self::DEFAULT_CATEGORIES,
                    'errors' => [$e->getMessage()],
                ]);
            }
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        $userId = (int)$_SESSION['user_id'];
        $expenseId = (int)$routeParams['id'];

        $expense = $this->expenseService->findById($expenseId);

        if(!$expense || $expense->userId !== $userId)
        {
            return $response->withStatus(403);
        }

        $this->expenseService->delete($expenseId);

        return $response
            ->withHeader('Location', '/expenses')
            ->withStatus(302);
    }
}
