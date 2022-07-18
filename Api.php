<?php

namespace Kneu;

use Generator;
use stdClass;

require_once __DIR__ . '/ApiException.php';
require_once __DIR__ . '/TransportException.php';
require_once __DIR__ . '/CurlException.php';
require_once __DIR__ . '/HttpException.php';
require_once __DIR__ . '/JsonException.php';

/**
 * Class Api
 * @package Kneu
 * @method Generator getFaculties   (int|array $filtersOrOffsetOrLimit = [], integer $offsetOrLimit, integer $limit = null))  Отримати список факультетів
 * @method Generator getDepartments (int|array $filtersOrOffsetOrLimit = [], integer $offsetOrLimit, integer $limit = null))  Отримати список кафедр
 * @method Generator getTeachers    (int|array $filtersOrOffsetOrLimit = [], integer $offsetOrLimit, integer $limit = null))  Отримати список викладачів
 * @method Generator getSpecialties (int|array $filtersOrOffsetOrLimit = [], integer $offsetOrLimit, integer $limit = null))  Отримати список спеціальностей
 * @method Generator getGroups      (int|array $filtersOrOffsetOrLimit = [], integer $offsetOrLimit, integer $limit = null)  Отримати список груп
 * @method Generator getStudents    (int|array $filtersOrOffsetOrLimit = [], integer $offsetOrLimit, integer $limit = null))  Отримати список студентів
 * @method stdClass getFaculty    (integer $id) Отримати факультет зі вказаним id
 * @method stdClass getDepartment (integer $id) Отримати кафедру зі вказаним id
 * @method stdClass getTeacher    (integer $id) Отримати викладача зі вказаним id
 * @method stdClass getSpecialty  (integer $id) Отримати спеціаліність зі вказаним id
 * @method stdClass getGroup      (integer $id) Отримати групу зі вказаним id
 * @method stdClass getStudent    (integer $id) Отримати студента зі вказаним id
 */
class Api
{
    const TOKEN_URL = 'https://auth.kneu.edu.ua/oauth/token';

    const API_URL   = 'https://auth.kneu.edu.ua/api/%s';

    /**
     * @var string
     */
    protected $accessToken;

    /**
     * @var resource
     */
    protected $ch;

    /** @var array */
    protected $contentRange = [];

    protected $entities = [
        'faculties' => 'faculty',
        'departments' => 'department',
        'teachers' => 'teacher',
        'specialties' => 'specialty',
        'groups' => 'group',
        'students' => 'student',
    ];

    protected $returnAssociative = false;

    /**
     * @param string|null $accessToken
     * @see Api::setAccessToken()
     */
    public function __construct($accessToken = null)
    {
        $this->ch = curl_init();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($this->ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');

        $this->setAccessToken($accessToken);
    }

    /**
     * Завершити процедуру oauth - отримати access_token для роботи з API
     * @param int $client_id ID додатку
     * @param string $client_secret Секрет додатку
     * @param string $code Код, отриманий з браузера користувача
     * @param string $redirect_uri URL, на який була виконана переадресація
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     * @return static
     */
    public static function createWithOauthToken($client_id, $client_secret, $code, $redirect_uri) {
        $self = new static();
        $self->oauthToken($client_id, $client_secret, $code, $redirect_uri);
        return $self;
    }


    /**
     * Авторизація сервера додатки - отримати access_token для роботи з API
     * @param int $client_id ID додатку
     * @param string $client_secret Секрет додатку
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     * @return static
     */
    public static function createWithServerToken($client_id, $client_secret) {
        $self = new static();
        $self->serverToken($client_id, $client_secret);
        return $self;
    }

    /**
     * Встановлює $accessToken
     * @param string $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $accessToken ? ['Authorization: Bearer ' . $accessToken] : []);
    }

    /**
     * Завершити процедуру oauth - отримати access_token для роботи з API
     * @param int $client_id ID додатку
     * @param string $client_secret Секрет додатку
     * @param string $code Код, отриманий з браузера користувача
     * @param string $redirect_uri URL, на який була виконана переадресація
     * @return stdClass
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     */
    public function oauthToken($client_id, $client_secret, $code, $redirect_uri)
    {
        curl_setopt($this->ch, CURLOPT_URL, self::TOKEN_URL);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect_uri
        ]));

        $answer = $this->execRequest();

        $this->setAccessToken($answer->access_token);

        return $answer;
    }

    /**
     * Авторизація сервера додатки - отримати access_token для роботи з API
     * @param int $client_id ID додатку
     * @param string $client_secret Секрет додатку
     * @return stdClass
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     */
    public function serverToken($client_id, $client_secret)
    {
        curl_setopt($this->ch, CURLOPT_URL, self::TOKEN_URL);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'client_credentials',
        ]));

        $answer = $this->execRequest();

        $this->setAccessToken($answer->access_token);

        return $answer;
    }

    /**
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     */
    protected function getEntity($entityName, $entityId)
    {
        return $this->request($entityName . '/' . $entityId);
    }

    /**
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     */
    protected function getEntitiesList($entityName, array $filters = [], $offset = 0, $limit = null)
    {
        if (!isset($limit) && isset($filters['limit'])) {
            $limit = (int)$filters['limit'];
        }

        if (!isset($offset) && isset($filters['offset'])) {
            $offset = (int)$filters['offset'];
        }

        do {
            $entities = $this->request($entityName . '?' . http_build_query($filters + [
                'limit' => (int)$limit,
                'offset' => (int)$offset ?: 0,
            ]));

            /** @var stdClass $entity */
            foreach($entities as $entity) {
                yield $entity;
            }

            $offset = $this->getContentRange('end') + 1;
            $total = $this->getContentRange('total');

            if (!is_null($limit)) {
                $limit -= count($entities);
                if ($limit <= 0) {
                    break;
                }
            }
        } while ($offset < $total);
    }

    /**
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     */
    public function __call($name, $arguments)
    {
        if (substr($name, 0, 3) == 'get') {
            $entityName = substr($name, 3);
            $entityName[0] = strtolower($entityName[0]);

            if (in_array($entityName, $this->entities)) {
                return $this->getEntity($entityName, $arguments[0]);

            } elseif (isset($this->entities[$entityName])) {
                var_dump($arguments);
                $arguments = array_slice($arguments, 0, 3);
                $filters = isset($arguments[0]) && is_array($arguments[0]) ? array_shift($arguments) : [];

                $numericArguments = array_filter($arguments, 'is_numeric');
                $limit = array_pop($numericArguments);
                $offset = array_pop($numericArguments);

                return $this->getEntitiesList($this->entities[$entityName], $filters, $offset, $limit);

            }
        }

        throw new \BadMethodCallException(__CLASS__ . '::' . $name);
    }

    /**
     * @return stdClass
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     */
    public function getUser()
    {
        return $this->request('user/me');
    }

    /**
     * @param string $method адреса методу
     * @param array $params POST-параметри
     * @return stdClass|array
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     */
    public function request($method, array $params = array())
    {
        $url = sprintf(self::API_URL, $method);

        curl_setopt($this->ch, CURLOPT_URL, $url);

        if ($params) {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        }

        return $this->execRequest();
    }

    /**
     * @return stdClass|array
     * @throws ApiException
     * @throws JsonException
     * @throws HttpException
     * @throws CurlException
     */
    protected function execRequest()
    {
        $response = curl_exec($this->ch);

        if (false === $response) {
            throw new CurlException($this->ch);
        }

        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $headersLength = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);

        $headers = substr($response, 0, $headersLength);
        $response = substr($response, $headersLength);

        $answer = json_decode($response, $this->returnAssociative);

        if (null === $answer) {
            throw new JsonException($response);
        }

        if (isset($answer->error)) {
            $message = isset($answer->error_description) ? $answer->error_description : $answer->error;
            throw new ApiException($message);
        }

        if ($httpCode < 200 || $httpCode > 299) {
            throw new HttpException($httpCode, $response);
        }

        $this->contentRange = $httpCode === 206 ? $this->parseContentRange($headers) : [];

        return $answer;
    }

    protected function parseContentRange($headers)
    {
        $pattern = '#^Content-Range:\s*items\s*(?P<start>[0-9]+)-(?P<end>[0-9]+)/(?<total>[0-9]+)\s*$#mi';

        if (preg_match($pattern, $headers, $match)) {
            unset($match[0], $match[1], $match[2], $match[3], $match[4]);
            return array_map('intval', $match);
        }
        
        return [];
    }

    /**
     * @param string|null $key NULL or enum("start", "end", "total")
     * @return array|integer|null
     */
    public function getContentRange($key = null)
    {
        return is_null($key) ? $this->contentRange :
            (isset($this->contentRange[$key]) ? $this->contentRange[$key] : null);
    }

    /** @return self */
    public function setReturnAssociative($returnAssociative = true)
    {
        $this->returnAssociative = $returnAssociative;
        return $this;
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }
}
