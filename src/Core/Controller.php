<?php
/**
 * XPLabs - Base Controller
 * All controllers extend from this class
 */

namespace XPLabs\Core;

class Controller
{
    protected Request $request;
    protected array $middleware = [];

    public function __construct()
    {
        $this->request = Request::fromGlobals();
    }

    /**
     * Return a view response
     */
    protected function view(string $view, array $data = [], int $statusCode = 200): Response
    {
        return Response::view($view, $data, $statusCode);
    }

    /**
     * Return a JSON response
     */
    protected function json(mixed $data, int $statusCode = 200, array $headers = []): Response
    {
        return Response::json($data, $statusCode, $headers);
    }

    /**
     * Return a success JSON response
     */
    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): Response
    {
        return Response::success($data, $message, $statusCode);
    }

    /**
     * Return an error JSON response
     */
    protected function error(string $message, int $statusCode = 400, mixed $data = null): Response
    {
        return Response::error($message, $statusCode, $data);
    }

    /**
     * Return a redirect response
     */
    protected function redirect(string $url, int $statusCode = 302): Response
    {
        return Response::redirect($url, $statusCode);
    }

    /**
     * Get request input
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $this->request->input($key, $default);
    }

    /**
     * Get route parameter
     */
    protected function param(string $key, mixed $default = null): mixed
    {
        return $this->request->param($key, $default);
    }

    /**
     * Get authenticated user ID
     */
    protected function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get authenticated user role
     */
    protected function userRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): Response|bool
    {
        if (!$this->isAuthenticated()) {
            if ($this->request->wantsJson()) {
                return $this->error('Unauthorized', 401);
            }
            return $this->redirect('/login.php');
        }
        return true;
    }

    /**
     * Require specific role
     */
    protected function requireRole(array|string $roles): Response|bool
    {
        $auth = $this->requireAuth();
        if ($auth instanceof Response) {
            return $auth;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        if (!in_array($this->userRole(), $roles)) {
            if ($this->request->wantsJson()) {
                return $this->error('Forbidden', 403);
            }
            return $this->redirect('/dashboard_student.php');
        }
        return true;
    }

    /**
     * Validate input data
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            $value = $this->request->input($field);
            
            foreach ($rulesList as $rule) {
                $parts = explode(':', $rule, 2);
                $ruleName = $parts[0];
                $ruleParam = $parts[1] ?? null;
                
                $error = $this->applyRule($field, $value, $ruleName, $ruleParam);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException(json_encode($errors));
        }
        
        return $this->request->only(array_keys($rules));
    }

    /**
     * Apply validation rule
     */
    private function applyRule(string $field, mixed $value, string $rule, ?string $param): ?string
    {
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    return ucfirst($field) . ' is required';
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return ucfirst($field) . ' must be a valid email';
                }
                break;
            case 'min':
                if (strlen($value) < (int) $param) {
                    return ucfirst($field) . ' must be at least ' . $param . ' characters';
                }
                break;
            case 'max':
                if (strlen($value) > (int) $param) {
                    return ucfirst($field) . ' must not exceed ' . $param . ' characters';
                }
                break;
            case 'numeric':
                if (!is_numeric($value)) {
                    return ucfirst($field) . ' must be a number';
                }
                break;
            case 'integer':
                if (!filter_var($value, FILTER_VALIDATE_INT)) {
                    return ucfirst($field) . ' must be an integer';
                }
                break;
            case 'in':
                if (!in_array($value, explode(',', $param))) {
                    return ucfirst($field) . ' must be one of: ' . $param;
                }
                break;
        }
        return null;
    }
}