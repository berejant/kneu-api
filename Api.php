<?php

namespace Kneu;

/**
 * Class Api
 * @package Kneu
 * @method array getFaculties      (integer $offset = null, integer $limit = null)  Отримати масив факультетів
 * @method array getDepartments    (integer $offset = null, integer $limit = null)  Отримати масив кафедр
 * @method array getTeachers       (integer $offset = null, integer $limit = null)  Отримати масив викладачів
 * @method array getSpecialties    (integer $offset = null, integer $limit = null)  Отримати масив спеціальностей
 * @method array getGroups         (integer $offset = null, integer $limit = null)  Отримати масив груп
 * @method \stdClass getFaculty    (integer $id) Отримати факультет зі вказаним id
 * @method \stdClass getDepartment (integer $id) Отримати кафедру зі вказаним id
 * @method \stdClass getTeacher    (integer $id) Отримати викладача зі вказаним id
 * @method \stdClass getSpecialty  (integer $id) Отримати спеціаліність зі вказаним id
 * @method \stdClass getGroup      (integer $id) Отримати групу зі вказаним id
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
    protected $contentRange = array();

    protected $entities = [
        'faculties' => 'faculty',
        'departments' => 'department',
        'teachers' => 'teacher',
        'specialties' => 'specialty',
        'groups' => 'group',
    ];

    /**
     * @param string|null $accessToken
     * @see Api::setAccessToken()
     */
    public function __construct($accessToken = null)
    {
        $this->ch = curl_init();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($this->ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');

        $this->setAccessToken($accessToken);
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
     * @return \stdClass
     * @throws CurlException
     * @throws JsonException
     * @throws ApiException
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
     * @return \stdClass
     * @throws CurlException
     * @throws JsonException
     * @throws ApiException
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

    protected function getEntity($entityName, $entityId)
    {
        return $this->request($entityName . '/' . $entityId);
    }

    protected function getEntitiesList($entityName, $offset = null, $limit = null)
    {
        return $this->request($entityName . '?' . http_build_query([
                'limit' => $limit,
                'offset' => $offset
            ]));
    }

    public function __call($name, $arguments)
    {
        if (substr($name, 0, 3) == 'get') {
            $entityName = substr($name, 3);
            $entityName[0] = strtolower($entityName[0]);

            if (in_array($entityName, $this->entities)) {
                return $this->getEntity($entityName, $arguments[0]);

            } elseif (isset($this->entities[$entityName])) {
                return $this->getEntitiesList(
                    $this->entities[$entityName],
                    isset($arguments[0]) ? $arguments[0] : null,
                    isset($arguments[1]) ? $arguments[1] : null
                );

            }
        }

        throw new \BadMethodCallException(__CLASS__ . '::' . $name);
    }

    /**
     * @return \stdClass
     */
    public function getUser()
    {
        return $this->request('user/me');
    }

    /**
     * @param string $method адреса методу
     * @param array $params POST-параметри
     * @return \stdClass|array
     * @throws CurlException
     * @throws JsonException
     * @throws ApiException
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
     * @return \stdClass|array
     * @throws CurlException
     * @throws JsonException
     * @throws ApiException
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

        $answer = json_decode($response);

        if (null === $answer) {
            throw new JsonException($response);
        }

        if (isset($answer->error)) {
            $message = isset($answer->error_description) ? $answer->error_description : $answer->error;
            throw new ApiException($message);
        }

        if ($httpCode < 200 || $httpCode > 299) {
            throw new ApiException('Waiting for 20x HTTP code, but receiving ' . $httpCode);
        }

        $this->contentRange = $httpCode === 206 ? $this->parseContentRange($headers) : [];

        return $answer;
    }

    protected function parseContentRange($headers): array
    {
        $pattern = '#^Content-Range:\s*items\s*(?P<start>[0-9]+)-(?P<end>[0-9]+)/(?<total>[0-9]+)\s*$#mi';

        if (preg_match($pattern, $headers, $match)) {
            unset($match[0], $match[1], $match[2], $match[3], $match[4]);
            return array_map('intval', $match);
        }
        
        return [];
    }

    /**
     * @param string $key NULL or enum("start", "end", "total")
     * @return array|integer|null
     */
    public function getContentRange($key = null)
    {
        return is_null($key) ? $this->contentRange : $this->contentRange[$key] ?? null;
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }
}
