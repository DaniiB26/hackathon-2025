<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Entity\User;
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

    private function getCategories(): array
    {
        return ['groceries', 'utilities', 'entertainment', 'transport', 'housing', 'healthcare', 'other'];
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
        $categories = $this->getCategories();

        return $this->render($response, 'expenses/create.twig', [
            'categories' => $categories
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
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
            $categories = $this->getCategories();


            return $this->render($response, 'expenses/create.twig', [
                'categories' => $categories,
                'errors' => [$e->getMessage()],
                'old' => $data,
            ]);
        }
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
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

        $categories = $this->getCategories();

        return $this->render($response, 'expenses/edit.twig', [
            'expense' => $expense,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
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
                'categories' => $this->getCategories(),
                'errors' => $errors
            ]);
        }

        $this->expenseService->update($expense, $amount, $description, $date, $category);

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
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

    public function import(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $userId = (int)$_SESSION['user_id'];
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['csv']) || $uploadedFiles['csv']->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write("Invalid CSV upload");
            return $response->withStatus(400);
        }

        $csvFile = $uploadedFiles['csv'];
        $user = new User($userId, '', '', new DateTimeImmutable()); //dummy user

        try {
            $importedCount = $this->expenseService->importFromCsv($user, $csvFile);
        } catch (RuntimeException $e) {
            $response->getBody()->write("Import failed: " . $e->getMessage());
            return $response->withStatus(400);
        }

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }
}
