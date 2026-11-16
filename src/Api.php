<?php

declare(strict_types=1);

namespace Kneu;

use Generator;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use stdClass;

/**
 * Клієнт OAuth 2.0 та RESTful API КНЕУ (https://auth.kneu.edu.ua).
 *
 * HTTP-транспорт — будь-який PSR-18 клієнт, запити будуються PSR-17 фабриками. Таймаути, проксі й сертифікати
 * центрів сертифікації — налаштування цього клієнта.
 *
 * Методи повертають об'єкти stdClass, після setReturnAssociative() — асоціативні масиви.
 *
 * @phpstan-type Entity stdClass|array<mixed>
 * @phpstan-type ContentRange array{start?: int, end?: int, total?: int}
 */
class Api
{
    public const TOKEN_URL = 'https://auth.kneu.edu.ua/oauth/token';

    public const API_URL = 'https://auth.kneu.edu.ua/api/%s';

    private ?string $accessToken = null;

    /** @var ContentRange */
    private array $contentRange = [];

    private bool $returnAssociative = false;

    /**
     * Конструктор final: фабрики create*() будують static, тож нащадок не може змінити його параметри.
     *
     * @param ClientInterface $httpClient PSR-18 клієнт, яким надсилаються всі запити
     * @param RequestFactoryInterface $requestFactory PSR-17 фабрика запитів
     * @param StreamFactoryInterface $streamFactory PSR-17 фабрика потоків (тіла POST-запитів)
     * @param string|null $accessToken access_token, отриманий раніше (див. setAccessToken())
     */
    final public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        ?string $accessToken = null,
    ) {
        $this->setAccessToken($accessToken);
    }

    /**
     * Завершити процедуру oauth - отримати access_token для роботи з API
     *
     * @param int|string $clientId ID додатку
     * @param string $clientSecret Секрет додатку
     * @param string $code Код, отриманий з браузера користувача
     * @param string $redirectUri URL, на який була виконана переадресація
     * @throws ApiException
     * @throws TransportException
     */
    public static function createWithOauthToken(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        int|string $clientId,
        string $clientSecret,
        string $code,
        string $redirectUri,
    ): static {
        $api = new static($httpClient, $requestFactory, $streamFactory);
        $api->oauthToken($clientId, $clientSecret, $code, $redirectUri);

        return $api;
    }

    /**
     * Авторизація сервера додатку - отримати access_token для роботи з API
     *
     * @param int|string $clientId ID додатку
     * @param string $clientSecret Секрет додатку
     * @throws ApiException
     * @throws TransportException
     */
    public static function createWithServerToken(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        int|string $clientId,
        string $clientSecret,
    ): static {
        $api = new static($httpClient, $requestFactory, $streamFactory);
        $api->serverToken($clientId, $clientSecret);

        return $api;
    }

    /**
     * Встановлює access_token, з яким надсилаються запити до API (заголовок Authorization: Bearer).
     * null або порожній рядок — запити без заголовка.
     */
    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Завершити процедуру oauth - отримати access_token для роботи з API
     *
     * @param int|string $clientId ID додатку
     * @param string $clientSecret Секрет додатку
     * @param string $code Код, отриманий з браузера користувача
     * @param string $redirectUri URL, на який була виконана переадресація
     * @return Entity відповідь сервера (access_token, token_type, expires_in …)
     * @throws ApiException
     * @throws TransportException
     */
    public function oauthToken(int|string $clientId, string $clientSecret, string $code, string $redirectUri): stdClass|array
    {
        return $this->requestToken([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /**
     * Авторизація сервера додатку - отримати access_token для роботи з API
     *
     * @param int|string $clientId ID додатку
     * @param string $clientSecret Секрет додатку
     * @return Entity відповідь сервера (access_token, token_type, expires_in …)
     * @throws ApiException
     * @throws TransportException
     */
    public function serverToken(int|string $clientId, string $clientSecret): stdClass|array
    {
        return $this->requestToken([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]);
    }

    /**
     * Отримати інформацію про користувача, якому видано поточний access_token (лише після oauthToken())
     *
     * @return Entity
     * @throws ApiException
     * @throws TransportException
     */
    public function getUser(): stdClass|array
    {
        return $this->request('user/me');
    }

    /**
     * Отримати список факультетів
     *
     * @param array<string, mixed>|int|null $filtersOrOffsetOrLimit фільтри, або зсув чи кількість
     * @return Generator<int, Entity, mixed, void>
     * @throws ApiException під час ітерації
     * @throws TransportException під час ітерації
     */
    public function getFaculties(array|int|null $filtersOrOffsetOrLimit = [], ?int $offsetOrLimit = null, ?int $limit = null): Generator
    {
        return $this->getList('faculty', $filtersOrOffsetOrLimit, $offsetOrLimit, $limit);
    }

    /**
     * Отримати список кафедр
     *
     * @param array<string, mixed>|int|null $filtersOrOffsetOrLimit фільтри, або зсув чи кількість
     * @return Generator<int, Entity, mixed, void>
     * @throws ApiException під час ітерації
     * @throws TransportException під час ітерації
     */
    public function getDepartments(array|int|null $filtersOrOffsetOrLimit = [], ?int $offsetOrLimit = null, ?int $limit = null): Generator
    {
        return $this->getList('department', $filtersOrOffsetOrLimit, $offsetOrLimit, $limit);
    }

    /**
     * Отримати список викладачів
     *
     * @param array<string, mixed>|int|null $filtersOrOffsetOrLimit фільтри, або зсув чи кількість
     * @return Generator<int, Entity, mixed, void>
     * @throws ApiException під час ітерації
     * @throws TransportException під час ітерації
     */
    public function getTeachers(array|int|null $filtersOrOffsetOrLimit = [], ?int $offsetOrLimit = null, ?int $limit = null): Generator
    {
        return $this->getList('teacher', $filtersOrOffsetOrLimit, $offsetOrLimit, $limit);
    }

    /**
     * Отримати список спеціальностей
     *
     * @param array<string, mixed>|int|null $filtersOrOffsetOrLimit фільтри, або зсув чи кількість
     * @return Generator<int, Entity, mixed, void>
     * @throws ApiException під час ітерації
     * @throws TransportException під час ітерації
     */
    public function getSpecialties(array|int|null $filtersOrOffsetOrLimit = [], ?int $offsetOrLimit = null, ?int $limit = null): Generator
    {
        return $this->getList('specialty', $filtersOrOffsetOrLimit, $offsetOrLimit, $limit);
    }

    /**
     * Отримати список академічних груп
     *
     * @param array<string, mixed>|int|null $filtersOrOffsetOrLimit фільтри, або зсув чи кількість
     * @return Generator<int, Entity, mixed, void>
     * @throws ApiException під час ітерації
     * @throws TransportException під час ітерації
     */
    public function getGroups(array|int|null $filtersOrOffsetOrLimit = [], ?int $offsetOrLimit = null, ?int $limit = null): Generator
    {
        return $this->getList('group', $filtersOrOffsetOrLimit, $offsetOrLimit, $limit);
    }

    /**
     * Отримати список студентів
     *
     * @param array<string, mixed>|int|null $filtersOrOffsetOrLimit фільтри, або зсув чи кількість
     * @return Generator<int, Entity, mixed, void>
     * @throws ApiException під час ітерації
     * @throws TransportException під час ітерації
     */
    public function getStudents(array|int|null $filtersOrOffsetOrLimit = [], ?int $offsetOrLimit = null, ?int $limit = null): Generator
    {
        return $this->getList('student', $filtersOrOffsetOrLimit, $offsetOrLimit, $limit);
    }

    /**
     * Отримати факультет зі вказаним id
     *
     * @return Entity
     * @throws ApiException
     * @throws TransportException
     */
    public function getFaculty(int $id): stdClass|array
    {
        return $this->request('faculty/' . $id);
    }

    /**
     * Отримати кафедру зі вказаним id
     *
     * @return Entity
     * @throws ApiException
     * @throws TransportException
     */
    public function getDepartment(int $id): stdClass|array
    {
        return $this->request('department/' . $id);
    }

    /**
     * Отримати викладача зі вказаним id
     *
     * @return Entity
     * @throws ApiException
     * @throws TransportException
     */
    public function getTeacher(int $id): stdClass|array
    {
        return $this->request('teacher/' . $id);
    }

    /**
     * Отримати спеціальність зі вказаним id
     *
     * @return Entity
     * @throws ApiException
     * @throws TransportException
     */
    public function getSpecialty(int $id): stdClass|array
    {
        return $this->request('specialty/' . $id);
    }

    /**
     * Отримати академічну групу зі вказаним id
     *
     * @return Entity
     * @throws ApiException
     * @throws TransportException
     */
    public function getGroup(int $id): stdClass|array
    {
        return $this->request('group/' . $id);
    }

    /**
     * Отримати студента зі вказаним id
     *
     * @return Entity
     * @throws ApiException
     * @throws TransportException
     */
    public function getStudent(int $id): stdClass|array
    {
        return $this->request('student/' . $id);
    }

    /**
     * Виклик довільного методу API
     *
     * @param string $uri адреса методу відносно https://auth.kneu.edu.ua/api/ (може містити query string)
     * @param array<string, mixed> $params POST-параметри; без них запит надсилається методом GET
     * @return stdClass|array<mixed>
     * @throws ApiException
     * @throws TransportException
     */
    public function request(string $uri, array $params = []): stdClass|array
    {
        $url = sprintf(self::API_URL, $uri);

        $request = $params
            ? $this->createFormRequest($url, $params)
            : $this->requestFactory->createRequest('GET', $url);

        if ($this->accessToken !== null && $this->accessToken !== '') {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->accessToken);
        }

        return $this->send($request);
    }

    /**
     * Діапазон із заголовка Content-Range останньої відповіді 206 (сторінки списку); [] після інших відповідей
     *
     * @param string|null $key null або "start", "end", "total"
     * @return ($key is null ? ContentRange : int|null)
     */
    public function getContentRange(?string $key = null): array|int|null
    {
        return $key === null ? $this->contentRange : ($this->contentRange[$key] ?? null);
    }

    /**
     * Повертати дані асоціативними масивами замість об'єктів stdClass
     */
    public function setReturnAssociative(bool $returnAssociative = true): static
    {
        $this->returnAssociative = $returnAssociative;

        return $this;
    }

    /**
     * Розбирає аргументи get<Entities>() так само, як 1.x: масив першим аргументом — фільтри; з чисел, що лишилися,
     * останнє — кількість (limit), передостаннє — зсув (offset).
     *
     * @param array<string, mixed>|int|null $filtersOrOffsetOrLimit
     * @return Generator<int, Entity, mixed, void>
     */
    private function getList(string $endpoint, array|int|null $filtersOrOffsetOrLimit, ?int $offsetOrLimit, ?int $limit): Generator
    {
        $filters = is_array($filtersOrOffsetOrLimit) ? $filtersOrOffsetOrLimit : [];
        $numbers = is_array($filtersOrOffsetOrLimit)
            ? [$offsetOrLimit, $limit]
            : [$filtersOrOffsetOrLimit, $offsetOrLimit, $limit];
        $numbers = array_values(array_filter($numbers, 'is_int'));

        $limit = array_pop($numbers);
        $offset = array_pop($numbers);

        return $this->getEntitiesList($endpoint, $filters, $offset, $limit);
    }

    /**
     * Сторінки списку: кожна наступна починається після кінця попередньої (Content-Range), поки не вичерпано total
     * або limit
     *
     * @param array<string, mixed> $filters
     * @return Generator<int, Entity, mixed, void>
     * @throws ApiException
     * @throws TransportException
     */
    private function getEntitiesList(string $endpoint, array $filters, ?int $offset, ?int $limit): Generator
    {
        if ($limit === null && isset($filters['limit']) && is_numeric($filters['limit'])) {
            $limit = (int) $filters['limit'];
        }

        if ($offset === null && isset($filters['offset']) && is_numeric($filters['offset'])) {
            $offset = (int) $filters['offset'];
        }

        // the query of every page takes them from $offset and $limit (1.x repeated the ones of the filters on every
        // page and requested the same page again and again)
        unset($filters['limit'], $filters['offset']);

        if ($limit === 0) {
            return;
        }

        do {
            $queryString = $this->buildQuery($filters + [
                'limit' => $limit,
                'offset' => $offset,
            ]);

            $page = $this->request($endpoint . ($queryString !== '' ? '?' . $queryString : ''));

            if (!is_array($page)) {
                throw new JsonException((string) json_encode($page, JSON_UNESCAPED_UNICODE), 'expected a list of ' . $endpoint);
            }

            if (!$page) { // reach end of list
                break;
            }

            foreach ($page as $entity) {
                if (!is_array($entity) && !$entity instanceof stdClass) {
                    throw new JsonException((string) json_encode($page, JSON_UNESCAPED_UNICODE), 'expected a list of ' . $endpoint);
                }

                yield $entity;
            }

            $end = $this->contentRange['end'] ?? null;
            $total = $this->contentRange['total'] ?? null;
            if ($end === null || $total === null) { // not a page: the whole list
                break;
            }

            $offset = $end + 1;

            if ($limit !== null) {
                $limit -= count($page);
                if ($limit <= 0) {
                    break;
                }
            }
        } while ($offset < $total);
    }

    /**
     * @param array<string, mixed> $params
     * @return Entity
     * @throws ApiException
     * @throws TransportException
     */
    private function requestToken(array $params): stdClass|array
    {
        $answer = $this->send($this->createFormRequest(self::TOKEN_URL, $params));

        $accessToken = is_array($answer) ? ($answer['access_token'] ?? null) : ($answer->access_token ?? null);
        if (!is_string($accessToken) || $accessToken === '') {
            throw new ApiException('The token response has no access_token');
        }

        $this->setAccessToken($accessToken);

        return $answer;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createFormRequest(string $url, array $params): RequestInterface
    {
        return $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream($this->buildQuery($params)));
    }

    /**
     * The body of the answer. A client may stream it after the headers (symfony/http-client's Psr18Client does): a
     * connection reset or a timeout while it streams surfaces as a warning and a cut body, or as a RuntimeException of
     * the stream — a network error as well (CurlException), not a JsonException of the cut body.
     *
     * @throws CurlException
     */
    private function readBody(ResponseInterface $response): string
    {
        $failure = null;
        set_error_handler(static function (int $type, string $message) use (&$failure): bool {
            $failure ??= $message;

            return true;
        }, E_WARNING | E_USER_WARNING);
        try {
            $stream = $response->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $body = $stream->getContents();
        } catch (\RuntimeException $e) {
            throw new CurlException($e);
        } finally {
            restore_error_handler();
        }

        if ($failure !== null) {
            throw new CurlException(new \RuntimeException('Reading the response failed: ' . $failure));
        }

        return $body;
    }

    /**
     * Надсилає запит і розбирає відповідь:
     *  - помилка PSR-18 клієнта (мережа, DNS, TLS, таймаут) — CurlException (це TransportException);
     *  - JSON з ключем "error" за будь-якого HTTP-коду — ApiException з error_description або error;
     *  - HTTP-код поза 2xx — HttpException з кодом і тілом відповіді;
     *  - тіло 2xx, яке не є JSON-об'єктом чи масивом — JsonException.
     *
     * @return stdClass|array<mixed>
     * @throws ApiException
     * @throws TransportException
     */
    private function send(RequestInterface $request): stdClass|array
    {
        $request = $request->withHeader('Accept', 'application/json');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new CurlException($e);
        }

        $statusCode = $response->getStatusCode();
        $body = $this->readBody($response);

        $jsonError = null;
        try {
            $answer = json_decode($body, $this->returnAssociative, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $answer = null;
            $jsonError = $e;
        }

        $error = $description = null;
        if (is_array($answer)) {
            $error = $answer['error'] ?? null;
            $description = $answer['error_description'] ?? null;
        } elseif ($answer instanceof stdClass) {
            $error = $answer->error ?? null;
            $description = $answer->error_description ?? null;
        }

        if ($error !== null) {
            throw new ApiException(self::stringify($description ?? $error));
        }

        if ($statusCode < 200 || $statusCode > 299) {
            throw new HttpException($statusCode, $body);
        }

        if ($jsonError !== null) {
            throw new JsonException($body, $jsonError->getMessage(), $jsonError->getCode(), $jsonError);
        }

        if (!is_array($answer) && !$answer instanceof stdClass) {
            throw new JsonException($body, 'expected an object or an array');
        }

        $this->contentRange = $statusCode === 206 ? $this->parseContentRange($response) : [];

        return $answer;
    }

    /**
     * @return ContentRange
     */
    private function parseContentRange(ResponseInterface $response): array
    {
        $pattern = '#^\s*items\s*(?P<start>\d+)-(?P<end>\d+)/(?P<total>\d+)\s*$#i';

        if (preg_match($pattern, $response->getHeaderLine('Content-Range'), $match)) {
            return [
                'start' => (int) $match['start'],
                'end' => (int) $match['end'],
                'total' => (int) $match['total'],
            ];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildQuery(array $params): string
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }

    private static function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
