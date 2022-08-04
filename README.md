# PHP client for KNEU RESTful API Service

This PHP Library provide programmatic user-friendly interface to work with [the KNEU RESTful API and OAuth 2.0](http://docs.kneu.apiary.io/).

Ця PHP-бібліотека забезпечує зручний програмний інтерфейс для роботи з [КНЕУ RESTful API та протоколом OAuth 2.0](http://docs.kneu.apiary.io/).

## Встановлення

Додати бібліотеку до Вашого проекту за допомогою Composer

    composer require kneu/api

## Опис методів

## `$api = Kneu\Api::createWithOauthToken($client_id, $client_secret, $code, $redirect_uri): Kneu\Api`

Завершити процедуру oauth - отримати access_token на основі отриманого від клієнта значення code.

 * **Parameters:**
   * `$client_id` — `int` — ID додатку, який надається адміністратором
   * `$client_secret` — `string` — Секрет додатку, який надається адміністратором
   * `$code` — `string` — Код отриманий з браузера користувача
   * `$redirect_uri` — `string` — URL стороннього додатку (домену), на який була виконана переадресація з параметром code.
 * **Returns:** `\Kneu\Api` - об'єкт классу `\Kneu\Api` для подальших викликів API

 * **Exceptions:**
   * `Kneu\CurlException`
   * `Kneu\JsonException`
   * `Kneu\ApiException`

## `$api = Kneu\Api::createWithServerToken($client_id, $client_secret): Kneu\Api`

Авторизація стороннього серверу для роботи з API (імпорту списку факультетів, кафедр, викладачів, академічних груп, спеціальностей).

 * **Parameters:**
   * `$client_id` — `int` — ID додатку, який надається адміністратором
   * `$client_secret` — `string` — Секрет додатку, який надається адміністратором
 * **Returns:** `\stdClass` - об'єкт классу `\Kneu\Api` для подальших викликів API

## `getAccessToken(): ?string`
Отримати access_token, який був отриманний після createWithServerToken або createWithOauthToken.

## `setReturnAssociative()`
За замовчуванням, методи повертають дані як об'єкти stdClass.
Метод `setReturnAssociative()` дозволяє змінити тип данних на асоціативний массив.

```php
$facultyStdClassObject = $api->getFaculty(1);
var_dump($facultyStdClassObject, $facultyStdClassObject->name);
<<<DUMP
object(stdClass)#2 (2) {
  ["id"]=> int(1)
  ["name"]=> string(44) "Економіки та управління"
}
string(44) "Економіки та управління"
DUMP;

$api->setReturnAssociative();
$facultyAssocArray = $api->getFaculty(1);
var_dump($facultyAssocArray, $facultyAssocArray['name']); =>
<<<DUMP
array(2) {
  ["id"]=> int(1)
  ["name"]=> string(44) "Економіки та управління"
}
string(44) "Економіки та управління"
DUMP;

```

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

## `getFaculties([array $filters = [],] [[integer $offset = null,] integer $limit = null]): Generator`
Отримати перелік факультетів
```php
$api->getFaculties();
$api->getFaculties($limit);
$api->getFaculties($offset, $limit);
<<<EXAMPLE
[
    {
        "id": 1,
        "name": "Економіки та управління"
    },
    ...
]
EXAMPLE;
```

## `getDepartments([array $filters = [],] [[integer $offset = null,] integer $limit = null]): Generator`
Отримати перелік кафедр
Дозволяє отримати перелік кафедр. Надає перелік:
- всіх кафедр відсортованних за id.
- кафедр вибраного факультету `faculty_id` відсортованних за назвою.

Об'єкт Кафедра надається разом з даними по зв'язанному об'єкту Факультет.


```php
$api->getDepartments(); // all
$api->getDepartments(['faculty_id' => 999]); // by faculty id
$api->getDepartments($filters);
$api->getDepartments($filters, $limit);
$api->getDepartments($filters, $offset, $limit);
$api->getDepartments($offset, $limit);
$api->getDepartments($limit);

<<<EXAMPLE
[
    {
        "id": 53,
        "faculty_id": 3,
        "name": "Адміністративного та фінансового права",
        "faculty": {
            "id": 3,
            "name": "Юридичний інститут"
        }
    },
    {
        "id": 57,
        "faculty_id": 3,
        "name": "Іноземних мов юридичного інституту",
        "faculty": {
            "id": 3,
            "name": "Юридичний інститут"
        }
    },
    ...
]
EXAMPLE;

```

## `getTeachers([array $filters = [],] [[integer $offset = null,] integer $limit = null]): Generator`
Дозволяє отримати перелік викладачів. Надає перелік:
- всіх викладачів відсортаванних за id.
- викладачів певного факультету `faculty_id` або кафедри `department_id` в алфавітному порядку за прізвищем.
  Об'єкт викладач надається разом даними зв'язаних об'єктів Кафедра та Користувач (User).

Увага! Обєкт User є необов'язковий. Присутний лише в разі, коли викладач вже зар'єструвався на сайті.

Внутрішня реалізація метода автоматично робить потрібну кількисть запитів до сервера, щоб отримати повний список викладачів.

```php
$api->getTeachers(); // all teachers
$api->getTeachers(['faculty_id' => 999]); // by faculty
$api->getTeachers(['department_id' => 999]); // by department
$api->getTeachers($filters);
$api->getTeachers($filters, $limit);
$api->getTeachers($filters, $offset, $limit);
$api->getTeachers($offset, $limit);
$api->getTeachers($limit);

<<<EXAMPLE
[
    {
        "id": 1105,
        "department_id": 21,
        "name": "Іваненко Іван Іванович",
        "first_name": "Іван",
        "middle_name": "Іванович",
        "last_name": "Іваненко",
        "image_url": "https:\/\/kneu.edu.ua\/files\/teacher\/teacher_photo\/thumbnail_1113333.jpg",
        "user": {
            "id": 5019,
            "login": "example@gmail.com"
        },
        "department": {
            "id": 21,
            "faculty_id": 3,
            "name": "Цивільного та трудового права"
        }
    },
    ...
]
EXAMPLE;


```
## `getSpecialties([array $filters = [],] [[integer $offset = null,] integer $limit = null]): Generator`
Дозволяє отримати перелік спеціальностей. Надає перелік:
- всіх спеціальностей сортованних за id
- спеціальностей певного факультету `faculty_id` сортованних за назвою.

Надається разом із даними зв'язаного об'єкту Факультет.
Внутрішня реалізація метода автоматично робить потрібну кількисть запитів до сервера, щоб отримати повний список спеціальностей.

```php
$api->getSpecialties(); // all specialties
$api->getSpecialties(['faculty_id' => 999]); // by faculty
$api->getSpecialties($filters);
$api->getSpecialties($filters, $limit);
$api->getSpecialties($filters, $offset, $limit);
$api->getSpecialties($offset, $limit);
$api->getSpecialties($limit);
<<<EXAMPLE
[
     {
        "id": 131,
        "faculty_id": 9,
        "code": "6701",
        "name": "Безпепека інформаційних і комунікаційних систем",
        "faculty": {
            "id": 9,
            "name": "Інститут інформаційних технологій в економіці"
        }
    },
    {
        "id": 173,
        "faculty_id": 9,
        "code": "6.051",
        "name": "Економіка",
        "faculty": {
            "id": 9,
            "name": "Інститут інформаційних технологій в економіці"
        }
    },
    ...
]
EXAMPLE;

 ```

## `getGroups([array $filters = [],] [[integer $offset = null,] integer $limit = null]): Generator`
Дозволяє отримати перелік академічних груп. Надає перелік:
- всіх академічних груп відсортованних за id;
- академичніних груп певної спеціальності `specialty_id` або факультету `faculty_id` відсортованних за назвою спеціальності та назвою групи

Об'єкт група надається разом із зв'заним об'єктом спеціальність.

```php
$api->getGroups(); // all groups
$api->getGroups(['faculty_id' => 999]); // by faculty
$api->getGroups(['specialty_id' => 999]); // by specialty
$api->getGroups($filters);
$api->getGroups($filters, $limit);
$api->getGroups($filters, $offset, $limit);
$api->getGroups($offset, $limit);
$api->getGroups($limit);
<<<EXAMPLE
[
    {
        "id": 13293,
        "specialty_id": 33,
        "course": 2,
        "name": "ПР.-201",
        "specialty": {
            "id": 33,
            "faculty_id": 18,
            "code": "7.03040101",
            "name": "Правознавство"
        }
    },
    {
        "id": 13297,
        "specialty_id": 36,
        "course": 2,
        "name": "ОА.-201",
        "specialty": {
            "id": 36,
            "faculty_id": 18,
            "code": "7.03050901",
            "name": "Облік і аудит"
        }
    }
    ...
]
EXAMPLE;
```

## `getStudents([array $filters = [],] [[integer $offset = null,] integer $limit = null]): Generator`
Дозволяє отримати перелік студентів. Надає перелік:
- всіх студентів, відсортованних за id
- студентів певної академічної групи `group_id` підсортованих в алфавітному порядку за прізвищем

Якщо студент зарєстрованний на сайті, додатково до додається інформацію про звязанну сутність користувач (User).
Якщо студент не зарєстрованний на сайті - інформація про User відсутня у результаті.
Внутрішня реалізація метода автоматично робить потрібну кількисть запитів до сервера, щоб отримати повний список груп.

```php
$api->getStudents(); // all students
$api->getStudents(['group_id' => 999]); // by group and order by name 
$api->getStudents($filters);
$api->getStudents($filters, $limit);
$api->getStudents($filters, $offset, $limit);
$api->getStudents($offset, $limit);
$api->getStudents($limit);

<<<EXAMPLE
[
    {
      "id": 444,
      "group_id": 123,
      "gradebook_id": "999999",
      "sex": "male",
      "name": "Іваненко Павло Володимирович",
      "first_name": "Павло",
      "middle_name": "Володимирович",
      "last_name": "Іваненко",
      "birthdate": "1992-07-13",
      "user": {
         "id": 32664,
         "login": "example@gmail.com"
      }
    },
    ...
]
EXAMPLE;


```

 * **Parameters:**
   * `$filters` — `array` — фільтр для вибірки певних об'єктів
   * `$offset` — `integer` — зсув об'єктів у видачі. Аналог SQL LIMIT [offset], [limit];
   * `$limit` — `integer` — кількість об'єктів у видачі (MAX = 2000). Аналог SQL LIMIT [offset], [limit];
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

## `getStudent(integer $id)`
Отримати студента зі вказаним id

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

$api = Kneu\Api::createWithOauthToken(__CLIENT_ID__, __CLIENT_SECRET__, filter_input(INPUT_GET, 'code'), $redirect_uri);
/*
    $redirect_uri - як правило це current url без параметрів code та scope.
    Якщо використаний фреймворк не дозволяє побудувати штатними засобами $redirect_uri,
    то його можна отримати наступним чином:

    $isSsl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
          || ($_SERVER['SERVER_PORT'] ?? $_SERVER['HTTP_X_FORWARDED_PORT'] ?? null) == 443;

    $redirect_uri = 'http' . ($isSsl ? 's' : '') . '//' . $_SERVER['HTTP_HOST']
                  . rtrim(preg_replace('#(code|state)=.*?($|\&)#', '', $_SERVER['REQUEST_URI']), '?');
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
      ["user"]=> object(stdClass)#5 (2) {
         ["id"] => int(5019)
         ["login"] => string(19) "example@gmail.com"
      }
      ["department"]=> object(stdClass)#6 (3) {
         ["id"] => int(21)
         ["faculty_id"] => int(3)
         ["name"] => string(55) "Цивільного та трудового права"
      }
    }
    */

    $department = $api->getDepartment($user->department_id); // or $teacher->department_id
    var_dump($department);
    /*
    object(stdClass) (3) {
      ["id"] => int(99)
      ["faculty_id"] => int(9)
      ["name"] => string(61) "Інформаційних систем в економіці"
      ["faculty"]=> object(stdClass)#3 (2) {
        ["id"] => int(3)
        ["name"] => string(35) "Юридичний інститут"
      }
    }
    */
```


### Авторизація серверу та імпорт бази даних

```php
require __DIR__ . '/vendor/autoload.php';

$api = new Kneu\Api::createWithServerToken(__CLIENT_ID__, __CLIENT_SECRET__)

try {

     foreach($api->getDepartments(['faculty_id' => 3]) as $teacher) {
         // do anything with $teacher...
     }
     

     /** @var stdClass $teacher */
     foreach($api->getTeachers(['department_id' => 21]) as $teacher) {
         // do anything with $teacher...
     }
     
     
     /** @var stdClass $student */
     foreach($api->getStudents(['group_id' => 17867]) as $student) {
         // do anything with $teacher...
     }


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
