<?php

namespace App\Services\XcVm;

use App\Services\XcVm\Exceptions\XcVmApiException;
use App\Services\XcVm\Exceptions\XcVmConnectionException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Minimal, dependency-free client for the XC-VM admin API.
 *
 * Request pattern (from the official OpenAPI spec):
 *   {protocol}://{server}:{port}/{accessCode}/?api_key={key}&action={action}
 *
 * GET  -> every parameter in the query string
 * POST -> `action` + `api_key` in the query string, remaining fields in the
 *         application/x-www-form-urlencoded body
 *
 * Every response is JSON: { "status": "STATUS_SUCCESS", "data": ... } or
 * { "status": "STATUS_FAILURE", "error": "..." }.
 */
class XcVmClient
{
    private GuzzleClient $http;

    public function __construct(?GuzzleClient $http = null)
    {
        $this->http = $http ?? new GuzzleClient([
            'timeout' => config('xcvm.timeout', 20),
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => true,
        ]);
    }

    // ── Connection ──────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return config('xcvm.enabled')
            && filled(config('xcvm.url'))
            && filled(config('xcvm.access_code'))
            && filled(config('xcvm.api_key'));
    }

    public function baseUrl(): string
    {
        $url = rtrim((string) config('xcvm.url'), '/');

        $port = (int) config('xcvm.port', 80);
        if ($port > 0 && $port !== 80 && ! str_contains($url, ':') && ! str_ends_with($url, '/')) {
            $url .= ":{$port}";
        }

        return $url . '/' . trim((string) config('xcvm.access_code'), '/') . '/';
    }

    /**
     * Ping XC-VM and verify the API key. Returns the admin user info array
     * (["id", "username", ...]) or throws when the connection fails.
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            throw new XcVmConnectionException('XC-VM is not configured. Set XC_VM_ENABLED, XC_VM_URL, XC_VM_ACCESS_CODE and XC_VM_API_KEY.');
        }

        $data = $this->request('user_info', 'GET');

        return is_array($data) ? $data : [];
    }

    // ── Authentication probe helpers ─────────────────────────────────────────

    /**
     * @return array{ok: bool, error: ?string, detail: ?array}
     */
    public function diagnostics(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'XC-VM is not configured', 'detail' => null];
        }

        try {
            $detail = $this->testConnection();

            return ['ok' => true, 'error' => null, 'detail' => $detail];
        } catch (XcVmConnectionException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'detail' => null];
        } catch (XcVmApiException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'detail' => $e->getData()];
        }
    }

    // ── Categories ───────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array
    {
        $data = $this->request('get_categories', 'GET');

        return is_array($data) ? $data : [];
    }

    public function createCategory(string $name, string $type = 'live', int $parentId = 0): int
    {
        $data = $this->request('create_category', 'POST', [
            'category_name' => $name,
            'category_type' => $type,
            'parent_id' => $parentId,
        ]);

        return (int) ($data['id'] ?? $data['category_id'] ?? 0);
    }

    public function editCategory(int $id, string $name, int $parentId = 0): bool
    {
        $this->request('edit_category', 'POST', [
            'id' => $id,
            'category_name' => $name,
            'parent_id' => $parentId,
        ]);

        return true;
    }

    public function deleteCategory(int $id): bool
    {
        $this->request('delete_category', 'POST', ['id' => $id]);

        return true;
    }

    // ── Streams (live channels) ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createStream(array $payload): int
    {
        $data = $this->request('create_stream', 'POST', $payload);

        return (int) ($data['id'] ?? $data['stream_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function editStream(int $id, array $payload): bool
    {
        $this->request('edit_stream', 'POST', array_merge(['id' => $id], $payload));

        return true;
    }

    public function deleteStream(int $id): bool
    {
        $this->request('delete_stream', 'POST', ['id' => $id]);

        return true;
    }

    public function startStream(int $id): bool
    {
        $this->request('start_stream', 'POST', ['id' => $id]);

        return true;
    }

    public function stopStream(int $id): bool
    {
        $this->request('stop_stream', 'POST', ['id' => $id]);

        return true;
    }

    // ── Bouquets ─────────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBouquets(): array
    {
        $data = $this->request('get_bouquets', 'GET');

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int, int>  $channelIds
     */
    public function createBouquet(string $name, array $channelIds = []): int
    {
        $body = ['bouquet_name' => $name];
        foreach ($channelIds as $channelId) {
            $body['bouquet_channels'][] = (int) $channelId;
        }

        $data = $this->request('create_bouquet', 'POST', $body);

        return (int) ($data['id'] ?? 0);
    }

    /**
     * @param  array<int, int>  $channelIds
     */
    public function editBouquet(int $id, string $name, array $channelIds = []): bool
    {
        $body = ['id' => $id, 'bouquet_name' => $name];
        foreach ($channelIds as $channelId) {
            $body['bouquet_channels'][] = (int) $channelId;
        }

        $this->request('edit_bouquet', 'POST', $body);

        return true;
    }

    public function deleteBouquet(int $id): bool
    {
        $this->request('delete_bouquet', 'POST', ['id' => $id]);

        return true;
    }

    // ── Lines (users) ────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLines(): array
    {
        $data = $this->request('get_lines', 'GET');

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createLine(array $payload): int
    {
        $data = $this->request('create_line', 'POST', $payload);

        return (int) ($data['id'] ?? $data['line_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function editLine(int $id, array $payload): bool
    {
        $this->request('edit_line', 'POST', array_merge(['id' => $id], $payload));

        return true;
    }

    public function deleteLine(int $id): bool
    {
        $this->request('delete_line', 'POST', ['id' => $id]);

        return true;
    }

    public function enableLine(int $id): bool
    {
        $this->request('enable_line', 'POST', ['id' => $id]);

        return true;
    }

    public function disableLine(int $id): bool
    {
        $this->request('disable_line', 'POST', ['id' => $id]);

        return true;
    }

    // ── Panel user accounts (admins / resellers) ─────────────────────────────
    // Clients themselves are managed as lines (create_line/edit_line/…); these
    // actions cover panel login accounts (member_group_id: 1=admin, 2=reseller,
    // 3=user). There is no get_users action in XC-VM — use getLines() for lines.

    /**
     * Create a panel account (admin, reseller or user).
     *
     * @param  array<string, mixed>  $payload
     */
    public function createUser(array $payload): int
    {
        $data = $this->request('create_user', 'POST', $payload);

        return (int) ($data['id'] ?? $data['user_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function editUser(int $id, array $payload): bool
    {
        $this->request('edit_user', 'POST', array_merge(['id' => $id], $payload));

        return true;
    }

    public function deleteUser(int $id): bool
    {
        $this->request('delete_user', 'POST', ['id' => $id]);

        return true;
    }

    public function enableUser(int $id): bool
    {
        $this->request('enable_user', 'POST', ['id' => $id]);

        return true;
    }

    public function disableUser(int $id): bool
    {
        $this->request('disable_user', 'POST', ['id' => $id]);

        return true;
    }

    // ── Movies (VOD) ─────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createMovie(array $payload): int
    {
        $data = $this->request('create_movie', 'POST', $payload);

        return (int) ($data['id'] ?? $data['movie_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function editMovie(int $id, array $payload): bool
    {
        $this->request('edit_movie', 'POST', array_merge(['id' => $id], $payload));

        return true;
    }

    public function deleteMovie(int $id): bool
    {
        $this->request('delete_movie', 'POST', ['id' => $id]);

        return true;
    }

    /**
     * XC-VM has no enable/disable actions for movies; use the real
     * start/stop actions. Movies are served on demand, so these are only
     * useful to warm (or tear down) a pre-started movie ingest process.
     */
    public function startMovie(int $id): bool
    {
        $this->request('start_movie', 'POST', ['id' => $id]);

        return true;
    }

    public function stopMovie(int $id): bool
    {
        $this->request('stop_movie', 'POST', ['id' => $id]);

        return true;
    }

    // ── Series ───────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createSeries(array $payload): int
    {
        $data = $this->request('create_series', 'POST', $payload);

        return (int) ($data['id'] ?? $data['series_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function editSeries(int $id, array $payload): bool
    {
        $this->request('edit_series', 'POST', array_merge(['id' => $id], $payload));

        return true;
    }

    public function deleteSeries(int $id): bool
    {
        $this->request('delete_series', 'POST', ['id' => $id]);

        return true;
    }

    // ── Episodes ─────────────────────────────────────────────────────────────

    /**
     * Create an episode. Per the XC-VM openapi spec the field names are
     * `series` (not series_id), `season_num`, `episode` (not episode_num) and
     * the media source is `stream_source` (not stream_url).
     *
     * @param  array<string, mixed>  $payload
     */
    public function createEpisode(array $payload): int
    {
        $data = $this->request('create_episode', 'POST', $payload);

        return (int) ($data['id'] ?? $data['episode_id'] ?? 0);
    }

    /**
     * Note: edit_episode takes the media source as `stream_url` (inconsistent
     * with create_episode which uses `stream_source`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function editEpisode(int $id, array $payload): bool
    {
        $this->request('edit_episode', 'POST', array_merge(['id' => $id], $payload));

        return true;
    }

    // ── Lookups (used by full sync / prune) ─────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStreams(): array
    {
        $data = $this->request('get_streams', 'GET');

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMovie(int $id): array
    {
        $data = $this->request('get_movie', 'GET', ['id' => $id]);

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSeries(int $id): array
    {
        $data = $this->request('get_series', 'GET', ['id' => $id]);

        return is_array($data) ? $data : [];
    }

    public function deleteEpisode(int $id): bool
    {
        $this->request('delete_episode', 'POST', ['id' => $id]);

        return true;
    }

    /**
     * XC-VM has no enable/disable actions for episodes; use the real
     * start/stop actions.
     */
    public function startEpisode(int $id): bool
    {
        $this->request('start_episode', 'POST', ['id' => $id]);

        return true;
    }

    public function stopEpisode(int $id): bool
    {
        $this->request('stop_episode', 'POST', ['id' => $id]);

        return true;
    }

    // ── System ───────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getSettings(array $params = []): array
    {
        $data = $this->request('get_settings', 'GET', $params);

        return is_array($data) ? $data : [];
    }

    /**
     * Clear the XC-VM cache. The panel exposes this as `reload_cache`.
     */
    public function clearCache(): bool
    {
        $this->request('reload_cache', 'POST');

        return true;
    }

    /**
     * Alias that mirrors the XC-VM action name.
     */
    public function reloadCache(): bool
    {
        return $this->clearCache();
    }

    /**
     * Core request executor with retry + response envelope parsing.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $action, string $method = 'GET', array $params = []): array
    {
        $uri = $this->baseUrl() . '?api_key=' . rawurlencode((string) config('xcvm.api_key'))
            . '&action=' . rawurlencode($action);

        $lastError = null;

        // Query-string copy of params for GET; the action param is already there.
        $query = $method === 'GET' ? $params : [];

        for ($attempt = 0; $attempt <= (int) config('xcvm.retries', 2); $attempt++) {
            try {
                $options = [
                    'query' => $query,
                    'headers' => [
                        'User-Agent' => 'iptv-middleware-xcvm-sync/1.0',
                        'Accept' => 'application/json',
                    ],
                ];

                if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
                    $options['form_params'] = $params;
                }

                $response = $this->http->request($method, $uri, $options);

                return $this->parseResponse($response, $action);
            } catch (ConnectException $e) {
                $lastError = new XcVmConnectionException(
                    "Cannot reach XC-VM at {$uri}: {$e->getMessage()}",
                    previous: $e
                );
            } catch (RequestException $e) {
                $lastError = new XcVmConnectionException(
                    "XC-VM request failed for action [{$action}]: {$e->getMessage()}",
                    previous: $e
                );
            } catch (XcVmApiException $e) {
                // API-level failures on 4xx/5xx with a body are retried only for 5xx.
                $lastError = $e;
                if ($e->getStatusCode() < 500) {
                    throw $e;
                }
            }

            usleep(300000 * ($attempt + 1)); // 300ms, 600ms, ...
        }

        throw $lastError ?? new XcVmConnectionException("XC-VM request failed for action [{$action}].");
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(mixed $response, string $action): array
    {
        $statusCode = (int) $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (is_array($decoded) && ($decoded['status'] ?? null) === 'STATUS_SUCCESS') {
            $data = $decoded['data'] ?? [];

            return is_array($data) ? $data : ['value' => $data];
        }

        $error = (string) ($decoded['error'] ?? $decoded['message'] ?? trim($body));
        if ($error === '') {
            $error = "Empty response from XC-VM for action [{$action}]";
        }

        Log::warning("XC-VM action [{$action}] failed", [
            'status_code' => $statusCode,
            'error' => $error,
            'response' => mb_substr($body, 0, 2000),
        ]);

        $e = new XcVmApiException($error, $statusCode, is_array($decoded) ? $decoded : null);
        $e->setAction($action);

        throw $e;
    }
}