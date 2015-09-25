<?php

namespace Kneu;

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

    /**
     * @param string|null $accessToken
     * @see Api::setAccessToken()
     */
    public function __construct ($accessToken = null) {
        $this->ch = curl_init();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);

        $this->setAccessToken($accessToken);
    }

    /**
     * Устаналивает $accessToken
     * @param string $accessToken
     */
    public function setAccessToken($accessToken) {
        $this->accessToken = $accessToken;

        if($this->accessToken) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $this->accessToken));
        } else {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array());
        }
    }

    /**
     * Завершить процедуру oauth - получить access_token для работы с API
     * @param $client_id ID приложения
     * @param $client_secret Секрет приложения
     * @param $code Код, полученный из браузера пользователя
     * @param $redirect_uri URI, на котрый была выполненна переадресация
     * @return \stdClass
     * @throws ApiException
     * @throws CurlException
     * @throws JsonException
     */
    public function oauthToken ($client_id, $client_secret, $code, $redirect_uri) {

        $params = array(
            'client_id'    => $client_id,
            'client_secret'=> $client_secret,
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $redirect_uri
        );

        curl_setopt($this->ch, CURLOPT_URL, self::TOKEN_URL);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($params));

        $answer = $this->execRequest();

        $this->setAccessToken($answer->access_token);

        return $answer;
    }

    /**
     * @return \stdClass
     */
    public function getUser () {
        return $this->request('user/me');
    }

    /**
     * @param int $id Идентификатор группы
     * @return \stdClass
     */
     public function getGroup ($id) {
         return $this->request('group/' . $id);
     }


    /**
     * @param $method адрес метода
     * @param array $params POST параметры
     * @return \stdClass
     * @throws ApiException
     * @throws CurlException
     * @throws JsonException
     */
    public function request ($method, array $params = array())
    {
        $url = sprintf(self::API_URL, $method);

        curl_setopt($this->ch, CURLOPT_URL, $url);

        if($params) {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        }

        return $this->execRequest();
    }

    /**
     * @return \stdClass
     * @throws ApiException
     * @throws CurlException
     * @throws JsonException
     */
    protected function execRequest() {
        $response = curl_exec($this->ch);

        if(false === $response) {
            throw new CurlException($this->ch);
        }

        $answer = json_decode($response);

        if( null === $answer) {
            throw new JsonException;
        }

        if(isset($answer->error)) {
            $message = isset($answer->error_description) ? $answer->error_description : $answer->error;
            throw new ApiException($message);
        }

        return $answer;
    }

    public function __destruct () {
        curl_close($this->ch);
    }
}