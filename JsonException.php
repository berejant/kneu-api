<?php
/**
 * @author Anton Berezhnoj <berejant@gmail.com>
 * @package kneu/api
 */

namespace Kneu;

class JsonException extends \Exception
{
    public function __construct ()
    {
        $code = json_last_error();

        if(function_exists('json_last_error_msg')) {
            $message = json_last_error_msg();
        } else {
            switch ($code) {
                case JSON_ERROR_NONE:
                    $message = 'Ошибок нет';
                    break;
                case JSON_ERROR_DEPTH:
                    $message = 'Достигнута максимальная глубина стека';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $message = 'Некорректные разряды или не совпадение режимов';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $message = 'Некорректный управляющий символ';
                    break;
                case JSON_ERROR_SYNTAX:
                    $message = 'Синтаксическая ошибка, не корректный JSON';
                    break;
                case JSON_ERROR_UTF8:
                    $message = 'Некорректные символы UTF-8, возможно неверная кодировка';
                    break;
                default:
                    $message = 'Неизвестная ошибка';
                    break;
            }
        }

        parent::__construct($message, $code);
    }
}