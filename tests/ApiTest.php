<?php

declare(strict_types=1);

namespace Kneu\Tests;

use Kneu\Api;
use Kneu\ApiException;
use Kneu\CurlException;
use Kneu\HttpException;
use Kneu\JsonException;
use Kneu\TransportException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Api::class)]
#[CoversClass(ApiException::class)]
#[CoversClass(TransportException::class)]
#[CoversClass(CurlException::class)]
#[CoversClass(HttpException::class)]
#[CoversClass(JsonException::class)]
final class ApiTest extends TestCase
{
    private MockHttpClient $client;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->client = new MockHttpClient();
        $this->factory = new Psr17Factory();
    }

    // --- tokens ---------------------------------------------------------------------------------------------------

    public function testServerTokenPostsClientCredentialsAsForm(): void
    {
        $this->client->willReturn(self::json(['access_token' => 'server-token', 'token_type' => 'Bearer', 'expires_in' => 7200]));

        $api = Api::createWithServerToken($this->client, $this->factory, $this->factory, 7, 's&cret key');

        self::assertSame('server-token', $api->getAccessToken());
        self::assertCount(1, $this->client->requests);
        $request = $this->client->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://auth.kneu.edu.ua/oauth/token', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame('client_id=7&client_secret=s%26cret+key&grant_type=client_credentials', (string) $request->getBody());
    }

    public function testOauthTokenPostsTheCodeAndTheRedirectUri(): void
    {
        $this->client->willReturn(self::json(['access_token' => 'user-token', 'token_type' => 'Bearer', 'expires_in' => 7200, 'user_id' => 5]));

        $api = Api::createWithOauthToken($this->client, $this->factory, $this->factory, '7', 'secret', 'the code', 'https://kneu.edu.ua/oauthComplete.php');

        self::assertSame('user-token', $api->getAccessToken());
        $request = $this->client->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(Api::TOKEN_URL, (string) $request->getUri());
        self::assertSame(
            'client_id=7&client_secret=secret&grant_type=authorization_code&code=the+code'
            . '&redirect_uri=https%3A%2F%2Fkneu.edu.ua%2FoauthComplete.php',
            (string) $request->getBody(),
        );
    }

    public function testTokenMethodsReturnTheAnswerOfTheServer(): void
    {
        $this->client->willReturn(
            self::json(['access_token' => 'first', 'expires_in' => 7200]),
            self::json(['access_token' => 'second', 'expires_in' => 3600]),
        );
        $api = new Api($this->client, $this->factory, $this->factory, 'stale');

        $answer = $api->serverToken(1, 'secret');
        self::assertInstanceOf(stdClass::class, $answer);
        self::assertSame(7200, $answer->expires_in);
        self::assertSame('first', $api->getAccessToken());
        self::assertFalse($this->client->requests[0]->hasHeader('Authorization'), 'the token call does not send the old token');

        $answer = $api->setReturnAssociative()->oauthToken(1, 'secret', 'code', 'https://example.org/');
        self::assertSame(['access_token' => 'second', 'expires_in' => 3600], $answer);
        self::assertSame('second', $api->getAccessToken());
    }

    public function testATokenAnswerWithoutAccessTokenIsAnApiException(): void
    {
        $this->client->willReturn(self::json(['token_type' => 'Bearer']));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('The token response has no access_token');

        Api::createWithServerToken($this->client, $this->factory, $this->factory, 1, 'secret');
    }

    public function testAnOauthErrorIsAnApiException(): void
    {
        $this->client->willReturn(self::json(['error' => 'invalid_grant', 'error_description' => 'The authorization code is invalid'], 400));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('The authorization code is invalid');

        Api::createWithOauthToken($this->client, $this->factory, $this->factory, 1, 'secret', 'used code', 'https://example.org/');
    }

    // --- headers and URLs -----------------------------------------------------------------------------------------

    public function testRequestsCarryTheBearerToken(): void
    {
        $this->client->willReturn(self::json(['id' => 999, 'type' => 'teacher', 'teacher_id' => 15]));
        $api = new Api($this->client, $this->factory, $this->factory, 'the-token');

        $user = $api->getUser();

        self::assertInstanceOf(stdClass::class, $user);
        self::assertSame(15, $user->teacher_id);
        $request = $this->client->requests[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://auth.kneu.edu.ua/api/user/me', (string) $request->getUri());
        self::assertSame('Bearer the-token', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('', (string) $request->getBody());
    }

    public function testNoAuthorizationHeaderWithoutAToken(): void
    {
        $this->client->willReturn(self::json(['id' => 1]), self::json(['id' => 1]), self::json(['id' => 1]));
        $api = new Api($this->client, $this->factory, $this->factory);

        $api->getFaculty(1);
        $api->setAccessToken('');
        $api->getFaculty(1);
        $api->setAccessToken('token');
        $api->setAccessToken(null);
        $api->getFaculty(1);

        foreach ($this->client->requests as $request) {
            self::assertFalse($request->hasHeader('Authorization'));
        }
        self::assertNull($api->getAccessToken());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function entityMethods(): iterable
    {
        yield 'faculty' => ['getFaculty', 'faculty'];
        yield 'department' => ['getDepartment', 'department'];
        yield 'teacher' => ['getTeacher', 'teacher'];
        yield 'specialty' => ['getSpecialty', 'specialty'];
        yield 'group' => ['getGroup', 'group'];
        yield 'student' => ['getStudent', 'student'];
    }

    #[DataProvider('entityMethods')]
    public function testEntityById(string $method, string $path): void
    {
        $this->client->willReturn(self::json(['id' => 21, 'name' => 'Цивільного та трудового права']));
        $api = new Api($this->client, $this->factory, $this->factory, 'token');

        $entity = $api->{$method}(21);

        self::assertInstanceOf(stdClass::class, $entity);
        self::assertSame('Цивільного та трудового права', $entity->name);
        self::assertSame(['https://auth.kneu.edu.ua/api/' . $path . '/21'], $this->client->urls());
        self::assertSame('GET', $this->client->requests[0]->getMethod());
    }

    public function testReturnAssociative(): void
    {
        $this->client->willReturn(self::json(['id' => 1, 'faculty' => ['id' => 3, 'name' => 'Юридичний інститут']]));
        $api = new Api($this->client, $this->factory, $this->factory);

        self::assertSame($api, $api->setReturnAssociative());
        self::assertSame(['id' => 1, 'faculty' => ['id' => 3, 'name' => 'Юридичний інститут']], $api->getDepartment(1));
    }

    public function testRequestWithParamsPostsAForm(): void
    {
        $this->client->willReturn(self::json(['ok' => true]));
        $api = new Api($this->client, $this->factory, $this->factory, 'token');

        $answer = $api->request('some/method?x=1', ['name' => 'Іван Іваненко', 'ids' => [1, 2]]);

        self::assertEquals((object) ['ok' => true], $answer);
        $request = $this->client->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://auth.kneu.edu.ua/api/some/method?x=1', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('Bearer token', $request->getHeaderLine('Authorization'));
        self::assertSame(http_build_query(['name' => 'Іван Іваненко', 'ids' => [1, 2]]), (string) $request->getBody());
    }

    // --- lists and pagination -------------------------------------------------------------------------------------

    public function testAListFollowsTheContentRangeToTheEnd(): void
    {
        $this->client->willReturn(
            self::page([['id' => 1], ['id' => 2]], 0, 1, 5),
            self::page([['id' => 3], ['id' => 4]], 2, 3, 5),
            self::page([['id' => 5]], 4, 4, 5),
        );
        $api = new Api($this->client, $this->factory, $this->factory, 'token');

        $teachers = $api->getTeachers();
        self::assertCount(0, $this->client->requests, 'nothing is requested before the iteration');

        self::assertEquals([
            0 => (object) ['id' => 1],
            1 => (object) ['id' => 2],
            2 => (object) ['id' => 3],
            3 => (object) ['id' => 4],
            4 => (object) ['id' => 5],
        ], iterator_to_array($teachers));
        self::assertSame([
            'https://auth.kneu.edu.ua/api/teachers',
            'https://auth.kneu.edu.ua/api/teachers?offset=2',
            'https://auth.kneu.edu.ua/api/teachers?offset=4',
        ], $this->client->urls());
        foreach ($this->client->requests as $request) {
            self::assertSame('Bearer token', $request->getHeaderLine('Authorization'));
        }
        self::assertSame(['start' => 4, 'end' => 4, 'total' => 5], $api->getContentRange());
        self::assertSame(5, $api->getContentRange('total'));
        self::assertNull($api->getContentRange('unknown'));
    }

    public function testFiltersOffsetAndLimit(): void
    {
        $this->client->willReturn(
            self::page([['id' => 11], ['id' => 12]], 10, 11, 20),
            self::page([['id' => 13]], 12, 12, 20),
        );
        $api = new Api($this->client, $this->factory, $this->factory);

        $departments = iterator_to_array($api->getDepartments(['faculty_id' => 3], 10, 3));

        self::assertEquals([(object) ['id' => 11], (object) ['id' => 12], (object) ['id' => 13]], $departments);
        self::assertSame([
            'https://auth.kneu.edu.ua/api/departments?faculty_id=3&limit=3&offset=10',
            'https://auth.kneu.edu.ua/api/departments?faculty_id=3&limit=1&offset=12',
        ], $this->client->urls());
    }

    /**
     * The arguments of get<Entities>() as 1.x read them: a leading array is the filters; of the numbers, the last is
     * the limit and the one before it the offset
     *
     * @return iterable<string, array{list<array<string, mixed>|int|null>, string}>
     */
    public static function listArguments(): iterable
    {
        yield 'none' => [[], 'groups'];
        yield 'limit' => [[5], 'groups?limit=5'];
        yield 'offset, limit' => [[10, 5], 'groups?limit=5&offset=10'];
        yield 'filters' => [[['specialty_id' => 33]], 'groups?specialty_id=33'];
        yield 'filters, limit' => [[['specialty_id' => 33], 5], 'groups?specialty_id=33&limit=5'];
        yield 'filters, offset, limit' => [[['specialty_id' => 33], 10, 5], 'groups?specialty_id=33&limit=5&offset=10'];
        yield 'null, limit' => [[null, 5], 'groups?limit=5'];
        yield 'limit, null' => [[5, null], 'groups?limit=5'];
        yield 'filters with limit and offset' => [[['limit' => 5, 'offset' => 10, 'faculty_id' => 9]], 'groups?faculty_id=9&limit=5&offset=10'];
        yield 'arguments win over the filters' => [[['limit' => 5, 'offset' => 10], 1, 2], 'groups?limit=2&offset=1'];
    }

    /**
     * @param list<array<string, mixed>|int|null> $arguments
     */
    #[DataProvider('listArguments')]
    public function testListArguments(array $arguments, string $uri): void
    {
        $this->client->willReturn(self::json([['id' => 1]]));
        $api = new Api($this->client, $this->factory, $this->factory);

        iterator_to_array($api->getGroups(...$arguments));

        self::assertSame(['https://auth.kneu.edu.ua/api/' . $uri], $this->client->urls());
    }

    public function testTheLimitAndOffsetOfTheFiltersMoveWithThePages(): void
    {
        // 1.x put the filters' offset into every page and asked for the same page forever
        $this->client->willReturn(
            self::page([['id' => 5], ['id' => 6]], 4, 5, 100),
            self::page([['id' => 7], ['id' => 8]], 6, 7, 100),
        );
        $api = new Api($this->client, $this->factory, $this->factory);

        $students = iterator_to_array($api->getStudents(['group_id' => 17867, 'offset' => 4, 'limit' => 4]));

        self::assertCount(4, $students);
        self::assertSame([
            'https://auth.kneu.edu.ua/api/students?group_id=17867&limit=4&offset=4',
            'https://auth.kneu.edu.ua/api/students?group_id=17867&limit=2&offset=6',
        ], $this->client->urls());
    }

    public function testAZeroLimitRequestsNothing(): void
    {
        $api = new Api($this->client, $this->factory, $this->factory);

        self::assertSame([], iterator_to_array($api->getSpecialties(0)));
        self::assertSame([], iterator_to_array($api->getSpecialties(['limit' => 0])));
        self::assertSame([], $this->client->requests);
    }

    public function testAListWithoutContentRangeIsTheWholeList(): void
    {
        $this->client->willReturn(self::json([['id' => 1], ['id' => 2]], 200, ['Content-Range' => 'items 0-1/5']));
        $api = new Api($this->client, $this->factory, $this->factory);

        self::assertCount(2, iterator_to_array($api->getFaculties()));
        self::assertCount(1, $this->client->requests);
        self::assertSame([], $api->getContentRange(), 'only a 206 response is a page');
    }

    public function testAnEmptyPageEndsTheList(): void
    {
        $this->client->willReturn(
            self::page([['id' => 1]], 0, 0, 3),
            self::json([], 206, ['Content-Range' => 'items 1-1/3']),
        );
        $api = new Api($this->client, $this->factory, $this->factory);

        self::assertCount(1, iterator_to_array($api->getFaculties()));
        self::assertCount(2, $this->client->requests);
    }

    public function testListsInAssociativeMode(): void
    {
        $this->client->willReturn(self::page([['id' => 1]], 0, 0, 2), self::page([['id' => 2]], 1, 1, 2));
        $api = (new Api($this->client, $this->factory, $this->factory))->setReturnAssociative();

        self::assertSame([['id' => 1], ['id' => 2]], iterator_to_array($api->getFaculties()));
    }

    public function testAListAnsweredWithAnObjectIsAJsonException(): void
    {
        $this->client->willReturn(self::json(['id' => 1]));
        $api = new Api($this->client, $this->factory, $this->factory);

        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Response JSON: expected a list of teachers');

        iterator_to_array($api->getTeachers());
    }

    // --- errors ---------------------------------------------------------------------------------------------------

    public function testANetworkErrorIsATransportException(): void
    {
        $networkError = new NetworkException(new Request('GET', Api::TOKEN_URL), 'Could not resolve host: auth.kneu.edu.ua', 6);
        $this->client->willReturn($networkError);

        try {
            Api::createWithServerToken($this->client, $this->factory, $this->factory, 1, 'secret');
            self::fail('No exception');
        } catch (TransportException $e) {
            self::assertInstanceOf(CurlException::class, $e, 'catch (CurlException) of 1.x still catches it');
            self::assertSame('Could not resolve host: auth.kneu.edu.ua', $e->getMessage());
            self::assertSame(6, $e->getCode());
            self::assertSame($networkError, $e->getPrevious());
            self::assertNull($e->getResponse());
        }
    }

    public function testAnErrorAnswerIsAnApiExceptionWhateverTheStatus(): void
    {
        $this->client->willReturn(
            self::json(['error' => 'not_found', 'error_description' => 'Вказана сутність не знайдена'], 404),
            self::json(['error' => 'server_error'], 200),
        );
        $api = new Api($this->client, $this->factory, $this->factory, 'token');

        try {
            $api->getTeacher(1);
            self::fail('No exception');
        } catch (ApiException $e) {
            self::assertSame('Вказана сутність не знайдена', $e->getMessage());
        }

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('server_error');
        $api->getTeacher(2);
    }

    public function testAnErrorAnswerIsAnApiExceptionInAssociativeModeToo(): void
    {
        $this->client->willReturn(self::json(['error' => 'invalid_token', 'error_description' => 'The access token expired'], 401));
        $api = (new Api($this->client, $this->factory, $this->factory, 'token'))->setReturnAssociative();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('The access token expired');

        $api->getUser();
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function httpErrors(): iterable
    {
        yield '404 HTML' => [404, '<html><body>Not Found</body></html>'];
        yield '401 JSON without error' => [401, '{"message":"Unauthenticated."}'];
        yield '500 empty' => [500, ''];
        yield '502 HTML' => [502, '<html><body>Bad Gateway</body></html>'];
        yield '302' => [302, ''];
    }

    #[DataProvider('httpErrors')]
    public function testAStatusOutside2xxIsAnHttpException(int $status, string $body): void
    {
        $this->client->willReturn(new Response($status, [], $body));
        $api = new Api($this->client, $this->factory, $this->factory, 'token');

        try {
            $api->getFaculty(1);
            self::fail('No exception');
        } catch (HttpException $e) {
            self::assertSame('Waiting for 20x HTTP code, but receiving ' . $status, $e->getMessage());
            self::assertSame($status, $e->getCode());
            self::assertSame($status, $e->getStatusCode());
            self::assertSame($body, $e->getResponse());
        }
    }

    public function testBadJsonIsAJsonException(): void
    {
        $this->client->willReturn(new Response(200, [], '{"id": 1,'));
        $api = new Api($this->client, $this->factory, $this->factory);

        try {
            $api->getFaculty(1);
            self::fail('No exception');
        } catch (JsonException $e) {
            self::assertSame('Response JSON: Syntax error', $e->getMessage());
            self::assertSame(JSON_ERROR_SYNTAX, $e->getCode());
            self::assertSame('{"id": 1,', $e->getResponse());
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notObjects(): iterable
    {
        yield 'empty body' => [''];
        yield 'null' => ['null'];
        yield 'number' => ['42'];
        yield 'string' => ['"ok"'];
    }

    #[DataProvider('notObjects')]
    public function testA2xxAnswerThatIsNoObjectOrArrayIsAJsonException(string $body): void
    {
        $this->client->willReturn(new Response(200, [], $body));
        $api = new Api($this->client, $this->factory, $this->factory);

        try {
            $api->getUser();
            self::fail('No exception');
        } catch (JsonException $e) {
            self::assertSame($body, $e->getResponse());
        }
    }

    public function testTheContentRangeOfAFailedRequestStays(): void
    {
        $this->client->willReturn(self::page([['id' => 1]], 0, 0, 1), new Response(500));
        $api = new Api($this->client, $this->factory, $this->factory);

        iterator_to_array($api->getFaculties());
        try {
            $api->getFaculty(1);
        } catch (HttpException) {
        }

        self::assertSame(['start' => 0, 'end' => 0, 'total' => 1], $api->getContentRange());
    }

    public function testASubclassIsBuiltByTheFactories(): void
    {
        $this->client->willReturn(self::json(['access_token' => 'token']));

        $api = ApiSubclass::createWithServerToken($this->client, $this->factory, $this->factory, 1, 'secret');

        self::assertSame(ApiSubclass::class, $api::class);
    }

    // --- helpers --------------------------------------------------------------------------------------------------

    /**
     * @param array<mixed> $data
     * @param array<string, string> $headers
     */
    private static function json(array $data, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private static function page(array $items, int $start, int $end, int $total): Response
    {
        return self::json($items, 206, ['Content-Range' => sprintf('items %d-%d/%d', $start, $end, $total)]);
    }
}
