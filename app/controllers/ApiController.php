<?php
/**
 * ApiController - JSON API endpoints for autocomplete (AP14).
 *
 * Provides search endpoints for @mention and #tag autocomplete.
 * All endpoints require authentication and return JSON.
 */
class ApiController
{
    /**
     * Search users for @mention autocomplete.
     * GET /?r=api_users&q=search_term
     *
     * Returns JSON: [{ "id": 1, "label": "Name", "email": "..." }, ...]
     */
    public function users(): void
    {
        Authz::require(Authz::API_USERS_SEARCH);
        $this->jsonHeaders();

        $query = trim($_GET['q'] ?? '');
        if ($query === '' || mb_strlen($query, 'UTF-8') < 1) {
            echo json_encode([]);
            return;
        }

        try {
            $search = '%' . $query . '%';
            $rows = DB::fetchAll(
                'SELECT id, name, email, role
                 FROM users
                 WHERE is_active = 1 AND (name LIKE ? OR email LIKE ?)
                 ORDER BY name ASC
                 LIMIT 10',
                [$search, $search]
            );

            $results = [];
            foreach ($rows as $row) {
                $results[] = [
                    'id'    => (int) $row['id'],
                    'label' => $row['name'],
                    'email' => $row['email'],
                ];
            }

            echo json_encode($results, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Logger::error('API users search failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Serverfehler']);
        }
    }

    /**
     * Search tags for #tag autocomplete.
     * GET /?r=api_tags&q=search_term
     *
     * Returns JSON: [{ "id": 1, "label": "tag-name" }, ...]
     */
    public function tags(): void
    {
        Authz::require(Authz::API_TAGS_SEARCH);
        $this->jsonHeaders();

        $query = trim($_GET['q'] ?? '');
        if ($query === '' || mb_strlen($query, 'UTF-8') < 1) {
            echo json_encode([]);
            return;
        }

        try {
            $search = '%' . mb_strtolower($query, 'UTF-8') . '%';
            $rows = DB::fetchAll(
                'SELECT id, name FROM tags WHERE name LIKE ? ORDER BY name ASC LIMIT 10',
                [$search]
            );

            $results = [];
            foreach ($rows as $row) {
                $results[] = [
                    'id'    => (int) $row['id'],
                    'label' => $row['name'],
                ];
            }

            echo json_encode($results, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Logger::error('API tags search failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Serverfehler']);
        }
    }

    /**
     * Set JSON response headers.
     */
    private function jsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
    }
}
