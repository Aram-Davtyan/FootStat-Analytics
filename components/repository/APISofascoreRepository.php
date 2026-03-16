<?php

namespace app\components\repository;

use Yii;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;
use yii\httpclient\Exception as HttpClientException;

/**
 * Репозиторий HTTP-доступа к Sofascore API.
 */
class APISofascoreRepository
{
    /**
     * Таймаут TCP-соединения (в секундах).
     */
    private const CONNECT_TIMEOUT_SECONDS = 3;

    /**
     * Полный таймаут HTTP-запроса (в секундах).
     */
    private const REQUEST_TIMEOUT_SECONDS = 8;

    /**
     * HTTP-клиент Yii.
     */
    private Client $client;

    /**
     * @param Client|null $client внешний клиент для тестов.
     */
    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? Yii::$app->get('sofascore');
    }

    /**
     * Выполняет HTTP-запрос к API и возвращает декодированный массив.
     *
     * @param string $method HTTP-метод запроса.
     * @param string $path путь относительно `baseUrl` или полный URL.
     * @param array<string, mixed> $data query/body параметры.
     * @return array<string, mixed>
     * @throws HttpClientException
     */
    public function request(string $method, string $path, array $data = []): array
    {
        $url = $this->buildUrl($path);
        $request = $this->client
            ->createRequest()
            ->setMethod($method)
            ->setUrl($url)
            ->addHeaders($this->buildAuthHeaders())
            ->setData($data)
            ->setOptions($this->buildRequestOptions());

        $response = $request->send();
        if (!$response->isOk) {
            $body = $response->getContent();
            throw new \RuntimeException("Sofascore API error ({$response->statusCode}): {$body}");
        }

        $decoded = $response->getData();
        if (!is_array($decoded)) {
            throw new \RuntimeException('Sofascore API: unexpected response format');
        }

        return $decoded;
    }

    /**
     * Выполняет GET-запрос.
     *
     * @param string $path путь запроса.
     * @param array<string, mixed> $query query-параметры.
     * @return array<string, mixed>
     * @throws HttpClientException
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * Формирует абсолютный URL запроса.
     *
     * @param string $path путь или абсолютный URL.
     */
    private function buildUrl(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        if (strpos($cleanPath, 'http://') === 0 || strpos($cleanPath, 'https://') === 0) {
            return $cleanPath;
        }

        $base = $this->client->baseUrl ?: 'https://sofascore.p.rapidapi.com';

        return rtrim($base, '/') . '/' . $cleanPath;
    }

    /**
     * Формирует заголовки авторизации RapidAPI.
     *
     * @return array<string, string>
     */
    private function buildAuthHeaders(): array
    {
        return [
            'x-rapidapi-key' => $_ENV['X_RAPIDAPI_KEY'] ?? '',
            'x-rapidapi-host' => $_ENV['X_RAPIDAPI_HOST'] ?? '',
        ];
    }

    /**
     * Формирует опции сетевого таймаута под текущий transport.
     *
     * @return array<string|int, mixed>
     */
    private function buildRequestOptions(): array
    {
        $transport = $this->client->getTransport();
        if ($transport instanceof CurlTransport) {
            return [
                'connectTimeout' => self::CONNECT_TIMEOUT_SECONDS,
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
            ];
        }

        return [
            'timeout' => self::REQUEST_TIMEOUT_SECONDS,
        ];
    }
}
