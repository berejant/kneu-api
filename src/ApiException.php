<?php

declare(strict_types=1);

namespace Kneu;

/**
 * Сервер відповів помилкою: JSON з ключем "error" (повідомлення — error_description або error), або у відповіді
 * на запит токена немає access_token.
 *
 * @package kneu/api
 */
class ApiException extends \Exception {}
