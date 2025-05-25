<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\ExpenseService;
use DateTimeImmutable;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 5;

    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $userId = (int)$_SESSION['user_id'];

        $query = $request->getQueryParams();
        $page = isset($query['page']) ? (int)$query['page'] : 1;
        $pageSize = isset($query['pageSize']) ? (int)$query['pageSize'] : self::PAGE_SIZE;
        $year = isset($query['year']) ? (int)$query['year'] : (int)date('Y');
        $month = isset($query['month']) ? (int)$query['month'] : (int)date('m');

        $expenses = $this->expenseService->list($userId, $year, $month, $page, $pageSize);

        $years = $this->expenseService->getAvailableYearsForUser($userId);

        $total = $this->expenseService->countBy($userId, $year, $month);
        $hasNextPage = $total > ($page * $pageSize);
        $totalPages = (int)ceil($total / $pageSize);

        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $expenses,
            'page'     => $page,
            'pageSize' => $pageSize,
            'year'     => $year,
            'month'    => $month,
            'years'    => $years,
            'total'    => $total,
            'hasNextPage' => $hasNextPage,
            'totalPages' => $totalPages,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $categories = ['groceries', 'utilities', 'entertainment', 'transport', 'housing', 'healthcare', 'other'];

        // TODO: obtain the list of available categories from configuration and pass to the view

        return $this->render($response, 'expenses/create.twig', [
            'categories' => $categories
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        // TODO: implement this action method to create a new expense

        // Hints:
        // - use the session to get the current user ID
        // - use the expense service to create and persist the expense entity
        // - rerender the "expenses.create" page with included errors in case of failure
        // - redirect to the "expenses.index" page in case of success

        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $userId = (int)$_SESSION['user_id'];
        $data = $request->getParsedBody();

        $amount = (float)($data['amount'] ?? 0);
        $description = trim($data['description'] ?? '');
        $category = trim($data['category'] ?? '');
        $dateString = $data['date'] ?? '';

        try {
            $date = new DateTimeImmutable($dateString);
        } catch (Exception) {
            $date = new DateTimeImmutable();
        }

        try {
            $this->expenseService->create(
                $userId,
                $amount,
                $description,
                $date,
                $category
            );

            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (RuntimeException $e) {
            $categories = ['groceries', 'utilities', 'entertainment', 'transport', 'housing', 'healthcare', 'other'];


            return $this->render($response, 'expenses/create.twig', [
                'categories' => $categories,
                'errors' => [$e->getMessage()],
                'old' => $data,
            ]);
        }
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        // TODO: implement this action method to display the edit expense page

        // Hints:
        // - obtain the list of available categories from configuration and pass to the view
        // - load the expense to be edited by its ID (use route params to get it)
        // - check that the logged-in user is the owner of the edited expense, and fail with 403 if not

        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $userId = (int)$_SESSION['user_id'];


        $expenseId = isset($routeParams['id']) ? (int)$routeParams['id'] : 0;
        if (!$expenseId) {
            return $response->withStatus(400); // Bad Request
        }

        $expense = $this->expenseService->getExpenseById($expenseId);

        if (!$expense) {
            return $response->withStatus(404); // Not Found
        }

        if ($expense->userId !== $userId) {
            return $response->withStatus(403); // Forbidden
        }

        $categories = ['groceries', 'utilities', 'entertainment', 'transport', 'housing', 'healthcare', 'other'];

        return $this->render($response, 'expenses/edit.twig', [
            'expense' => $expense,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        // TODO: implement this action method to update an existing expense

        // Hints:
        // - load the expense to be edited by its ID (use route params to get it)
        // - check that the logged-in user is the owner of the edited expense, and fail with 403 if not
        // - get the new values from the request and prepare for update
        // - update the expense entity with the new values
        // - rerender the "expenses.edit" page with included errors in case of failure
        // - redirect to the "expenses.index" page in case of success

        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $userId = (int)$_SESSION['user_id'];

        $expenseId = isset($routeParams['id']) ? (int)$routeParams['id'] : 0;
        if (!$expenseId) {
            return $response->withStatus(400);
        }

        $expense = $this->expenseService->getExpenseById($expenseId);
        if (!$expense) {
            return $response->withStatus(404);
        }

        if ($expense->userId !== $userId) {
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0;
        $description = trim($data['description'] ?? '');
        $category = trim($data['category'] ?? '');
        $dateString = $data['date'] ?? '';
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateString) ?: new DateTimeImmutable();

        $errors = [];
        if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';
        if (empty($category)) $errors[] = 'Category is required.';
        if (empty($description)) $errors[] = 'Description is required.';
        if ($date > new \DateTimeImmutable()) $errors[] = 'Date cannot be in the future.';

        if (!empty($errors)) {
            return $this->render($response, 'expenses/edit.twig', [
                'expense' => $expense,
                'categories' => ['Groceries', 'Utilities', 'Transport', 'Entertainment'],
                'errors' => $errors
            ]);
        }

        $this->expenseService->update($expense, $amount, $description, $date, $category);

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        // TODO: implement this action method to delete an existing expense

        // - load the expense to be edited by its ID (use route params to get it)
        // - check that the logged-in user is the owner of the edited expense, and fail with 403 if not
        // - call the repository method to delete the expense
        // - redirect to the "expenses.index" page

        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $userId = (int)$_SESSION['user_id'];

        $expenseId = isset($routeParams['id']) ? (int)$routeParams['id'] : 0;
        if (!$expenseId) {
            return $response->withStatus(400); // Bad Request
        }

        $expense = $this->expenseService->getExpenseById($expenseId);
        if (!$expense) {
            return $response->withStatus(404); // Not Found
        }

        if ($expense->userId !== $userId) {
            return $response->withStatus(403); // Forbidden
        }

        $this->expenseService->deleteExpenseById($expenseId);

        return $response->withHeader('Location', '/expenses')->withStatus(302);

        return $response;
    }
}
