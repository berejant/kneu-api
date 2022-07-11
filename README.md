# PHP client for KNEU RESTful API Service

This PHP Library provide programmatic user-friendly interface to work with [the KNEU RESTful API and OAuth 2.0](http://docs.kneu.apiary.io/).

Ця PHP-бібліотека забезпечує зручний програмний інтерфейс для роботи з [КНЕУ RESTful API та протоколом OAuth 2.0](http://docs.kneu.apiary.io/).

## Встановлення

Додати бібліотеку до Вашого проекту за допомогою Composer

    composer require kneu/api

## Опис методів

### Ініціалізація об'єкту для роботи з API

```php
$api = new Kneu\Api;
```

#### `__construct($accessToken = null)`

 * **Parameters:** `$accessToken` — `string|null` — Токен для роботи з API.
 * **See also:** Api::setAccessToken(), Api::oauthToken(), Api::serverToken()

### `setAccessToken($accessToken)`

Встановлює $accessToken

 * **Parameters:** `$accessToken` — `string`

## `oauthToken($client_id, $client_secret, $code, $redirect_uri)`

Завершити процедуру oauth - отримати access_token на основі отриманого від клієнта значення code.

 * **Parameters:**
   * `$client_id` — `int` — ID додатку, який надається адміністратором
   * `$client_secret` — `string` — Секрет додатку, який надається адміністратором
   * `$code` — `string` — Код отриманий з браузера користувача
   * `$redirect_uri` — `string` — URL стороннього додатку (домену), на який була виконана переадресація з параметром code.
 * **Returns:** `\stdClass` - містить властивості:
   * **access_token** - `string` - безпосередньо код access_token
   * **token_type** - `string` - "Bearer"
   * **expire_in** - `integer` -  час життя access_token в секундах
   * **user_id** - `integer` - ідентифікатор користувача

 * **Exceptions:**
   * `Kneu\CurlException`
   * `Kneu\JsonException`
   * `Kneu\ApiException`

## `serverToken($client_id, $client_secret)`

Авторизація стороннього серверу для роботи з API (імпорту списку факультетів, кафедр, викладачів, академічних груп, спеціальностей).

 * **Parameters:**
   * `$client_id` — `int` — ID додатку, який надається адміністратором
   * `$client_secret` — `string` — Секрет додатку, який надається адміністратором
 * **Returns:** `\stdClass` - аналогічно до результату виконання oauthToken(), властивість **user_id** відсутня
 * **Exceptions:**
   * `Kneu\CurlException`
   * `Kneu\JsonException`
   * `Kneu\ApiException`

## `request($method, array $params = array())`

Виклик довільного API-методу.

 * **Parameters:**
   * `$method` — `string` — адреса методу
   * `$params` — `array` — POST параметри
 * **Returns:** `\stdClass|array`
 * **Exceptions:**
   * `Kneu\CurlException`
   * `Kneu\JsonException`
   * `Kneu\ApiException`

## `getFaculties(integer $offset = null, integer $limit = null)`
Отримати перелік факультетів
## `getDepartments(integer $offset = null, integer $limit = null)`
Отримати перелік кафедр

## `getTeachers(integer $offset = null, integer $limit = null)`
Отримати перелік викладачів

## `getSpecialties(integer $offset = null, integer $limit = null)`
Отримати перелік спеціальностей

## `getGroups(integer $offset = null, integer $limit = null)`
Отримати перелік академічних груп

 * **Parameters:**
   * `$offset` — `integer` — зсув вибірки від початку. Аналог SQL LIMIT [offset], [limit];
   * `$limit` — `integer` — кількість об'єктів у видачі (MAX = 500). Аналог SQL LIMIT [offset], [limit];
 * **Returns:** `array`
 * **Exceptions:**
   * `Kneu\CurlException`
   * `Kneu\JsonException`
   * `Kneu\ApiException`

## `getFaculty(integer $id)`
Отримати факультет зі вказаним id

## `getDepartment(integer $id)`
Отримати кафедру зі вказаним id

## `getTeacher(integer $id)`
Отримати викладача зі вказаним id

## `getSpecialty(integer $id)`
Отримати спеціальність зі вказаним id

## `getGroup(integer $id)`
Отримати групу зі вказаним id

## `getUser()`

Отримати інформацію про поточного користувача з поточним access_token.
Інформація про користувача доступна лише після виклику oauthToken().
При використанні авторизації серверу (serverToken()) - інформація про користувача не надається.

 * **Returns:** `\stdClass` — має властивості:
   * **id** - `integer` - ідентифікатор користувача,
   * **email** - `string`,
   * **last_name** - `string`,
   * **first_name** - `string`,
   * **middle_name** - `string`,
   * **type** - `enum("student", "teacher", "simple")`
   * **student_id** - `null|integer` - ідентифікатор облікового запису студента (не номер залікової книжки)
   * **group_id** - `null|integer` - ідентифікатор академічної групи студента
   * **teacher_id** - `null|integer` - ідентифікатор викладача
   * **department_id** - `null|integer` - ідентифікатор кафедри, до якої належить викладач
   * **sex** - `null|enum("male", "female")` - Стать (чоловік/жінка), доступно лише для студентів


## `getContentRange($key)`

Дозволяє отримати Meta-інформацію про загальну кількість об'єктів певної сутності (для переліку викладачів, спеціальностей, факультетів тощо).
Інформація надається із заголовку Content-Range, тому метод `getContentRange()` може надати інформацію лише після виконання методу (запиту) на отримання переліку об'єктів певної сутності.
Наприклад, метод `getContentRange()` доцільно викликати після виконання методів `getTeachers()`, `getGroups()`, `getSpecialities()` тощо.
Детальніше застосування методу `getContentRange()` подано в прикладі коду нижче (у розділі [**Авторизація серверу та імпорт бази даних**](#Авторизація-серверу-та-імпорт-бази-даних)).

 * **Parameters:** `$key` — `string` — enum("total", "start", "end")
   * **total** - загальна кількість об'єктів (total count from database)
   * **start** - початок зсуву даних починаючи під початку (від 0). Аналог SQL LIMIT [start], 100. Іншими словам - індексу першого об'єкту з останнього запиту в загальному переліку об'єктів.
   * **end** - кінець зсуву даних. [end] = [start] + [limit] - 1 за аналогією з SQL LIMIT [start], [limit]. Іншими словам - індексу останнього об'єкту з останнього запиту в загальному переліку об'єктів.
   * Якщо значення `$key` не задано, то метод поверне масив з ключами **start**, **end**, **total**.
 * **Returns:** `array|integer|null`

## Приклад використання

### Авторизація користувача та імпорт даних про користувача
```php
require __DIR__ . '/vendor/autoload.php';

$api = new Kneu\Api;

$accessToken = $api->oauthToken(__CLIENT_ID__, __CLIENT_SECRET__, filter_input(INPUT_GET, 'code'), $redirect_uri);
/*
    $redirect_uri - як правило це current url без параметрів code та scope.
    Якщо використаний фреймворк не дозволяє побудувати штатними засобами $redirect_uri,
    то його можна отримати наступним чином:

    $isSsl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
          || ($_SERVER['SERVER_PORT'] ?? $_SERVER['HTTP_X_FORWARDED_PORT'] ?? null) == 443;

    $redirect_uri = 'http' . ($isSsl ? 's' : '') . '//' . $_SERVER['HTTP_HOST']
                  . rtrim(preg_replace('#(code|state)=.*?($|\&)#', '', $_SERVER['REQUEST_URI']), '?');
*/

var_dump($accessToken);
/*
object(stdClass) (4) {
  ["access_token"] => string(32) "63f02b799aea683bc045adadf5b4x429"
  ["token_type"] => string(6) "Bearer"
  ["expires_in"] => int(7200)
  ["user_id"] => int(999)
}
*/

$user = $api->getUser();
var_dump($user);
/*
object(stdClass) (9) {
  ["id"] => int(999)
  ["email"] => string(18) "sample@exampla.com"
  ["last_name"] => string(6) "Іванов"
  ["first_name"] => string(4) "Іван"
  ["middle_name"] => string(8) "Іванович"

  ["type"] => string(6) "simple"

  ...

  ["type"] => string(7) "student"
  ["student_id"] => int(99999)
  ["group_id"] => int(9999)
  ["sex"] => string(4) "male"

  ...

  ["type"] => string(7) "teacher"
  ["teacher_id"] => int(9999)
  ["department_id"] => int(99)

}
*/

if('student' == $user->type) {
    $group = $api->getGroup($user->group_id);
    var_dump($group);

    /*
    object(stdClass) (5) {
      ["id"] => int(999)
      ["name"] => string(10) "ЕЕЕ-999"
      ["course"] => int(5) // п'ятий курс
      ["specialty"] => object(stdClass) (3) {
        ["id"] => int(214)
        ["code"] => string(5) "8.122"
        ["name"] => string(85) "Комп’ютерні науки та інформаційні технології"
      }
      ["faculty"] => object(stdClass) (2) {
        ["id"] => int(9)
        ["name"] => string(63) "Інформаційних систем і технологій"
      }
    }
    */

} elseif ('teacher' == $user->type) {
    $teacher = $api->getTeacher($user->teacher_id);
    var_dump($teacher);
    /*
    object(stdClass)#4 (5) {
      ["id"] => int(999)
      ["department_id"] => int(43)
      ["first_name"] => string(4) "Іван"
      ["middle_name"] => string(8) "Іванович"
      ["last_name"] => string(6) "Іванов"
    }
    */

    $department = $api->getDepartment($user->department_id); // or $teacher->department_id
    var_dump($department);
    /*
    object(stdClass) (3) {
      ["id"] => int(99)
      ["faculty_id"] => int(9)
      ["name"] => string(61) "Інформаційних систем в економіці"
    }
    */
```


### Авторизація серверу та імпорт бази даних

```php
require __DIR__ . '/vendor/autoload.php';

$api = new Kneu\Api;

$token = $api->serverToken(__CLIENT_ID__, __CLIENT_SECRET__);

try {
    $offset = 0;
    do {
        // отримання списку викладачів партіями по 500 записів.
        // За замовчування limit = 500. Ліміт можно вказати довільний, але не більше 500.
        $teachers = $api->getTeachers($offset /* , $limit = 500 */);

        /** @var stdClass $teacher */
        foreach($teachers as $teacher) {
            // do anything with $teacher...
        }

        $offset = $api->getContentRange('end') + 1;
        $total = $api->getContentRange('total');

    } while ($offset < $total);

/* Обробка помилок - або кожну помилку окремо або один єдиний блок catch(\Exception $e) */
} catch (\Kneu\CurlException $e) {
    var_dump($e);

} catch (\Kneu\JsonException $e) {
    var_dump($e);

} catch (\Kneu\ApiException $e) {
    var_dump($e);

/*  або замість трьох блоків catch - один, який перехоплює будь-які виключення Exception */
} catch (\Exception $e) {
    var_dump($e);
}
```
