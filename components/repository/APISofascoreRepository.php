<?php 

namespace app\components\repository;

use Yii;
use yii\httpclient\Client;
use yii\httpclient\Exception as HttpClientException;

/**
 * Работает с HTTP-клиентом sofascore: отправка запросов, разбор ошибок.
 */
class APISofascoreRepository
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: Yii::$app->get('sofascore');
    }

    /**
        * Универсальный метод запроса.
        *
        * @param string $method HTTP-метод (GET/POST/...)
        * @param string $path   Путь относительно baseUrl клиента
        * @param array $data    Параметры запроса (query/body)
        * @return array
        * @throws \RuntimeException|HttpClientException
        */
    public function request(string $method, string $path, array $data = []): array
    {
         // Собираем абсолютный URL, даже если baseUrl компонента не задан
        $cleanPath = ltrim($path, '/');
        $base = $this->client->baseUrl ?: 'https://sofascore.p.rapidapi.com';
        $url = (strpos($cleanPath, 'http://') === 0 || strpos($cleanPath, 'https://') === 0)
            ? $cleanPath
            : rtrim($base, '/') . '/' . $cleanPath;

        // Гарантируем наличие auth-заголовков даже если компонент сконфигурирован не полностью
        $authHeaders = [
            'x-rapidapi-key' => $_ENV['X_RAPIDAPI_KEY'] ? $_ENV['X_RAPIDAPI_KEY'] : '',
            'x-rapidapi-host' => $_ENV['X_RAPIDAPI_HOST'] ? $_ENV['X_RAPIDAPI_HOST'] : '',
        ];

        $response = $this->client@
            ->createRequest()
            ->setMethod($method)
            ->setUrl($url)
            ->addHeaders($authHeaders)
            ->setData($data)
            ->send();

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

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * Экспонируем клиент (используем для фолбэков).
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
