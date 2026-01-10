# Документация по классам и методам

## components/services/APISofascoreServices.php
### Класс: APISofascoreServices
Назначение: сервис для получения и агрегации данных о игроках/командах из Sofascore через репозиторий.

#### Свойства
- `private APISofascoreRepository $repository` - источник сетевых запросов.

#### Методы
```
__construct(?APISofascoreRepository $repository = null)
```
- Назначение: инициализация сервиса.
- Параметры: `repository` - внешний репозиторий; если не передан, создается новый.
- Возвращает: ничего.

```
getPlayerDetails($playerId): array
```
- Назначение: получить детали игрока.
- Параметры: `playerId` - ID игрока.
- Возвращает: массив данных API.
- Исключения: пробрасывает ошибки репозитория.

```
getPlayerImage($playerId): array
```
- Назначение: получить URL/данные изображения игрока через API.
- Параметры: `playerId` - ID игрока.
- Возвращает: массив ответа API.

```
getPlayerCharacteristics($playerId): array
```
- Назначение: получить характеристики игрока.
- Параметры: `playerId` - ID игрока.
- Возвращает: массив ответа API.

```
getPlayerRatings(int $playerId, ?int $tournamentId = null, ?int $seasonId = null): array
```
- Назначение: получить рейтинги игрока.
- Параметры: `playerId` - ID игрока; `tournamentId` и `seasonId` обязательны для запроса.
- Возвращает: массив рейтингов; если нет обязательных параметров, возвращает пустой массив.

```
getPlayerAllStatistics($playerId): array
```
- Назначение: получить статистику игрока (полная).
- Параметры: `playerId` - ID игрока.
- Возвращает: массив ответа API.

```
getPlayerStatistics($playerId): array
```
- Назначение: получить статистику игрока (обычная).
- Параметры: `playerId` - ID игрока.
- Возвращает: массив ответа API.

```
getPlayerStatisticsSeasons($playerId): array
```
- Назначение: получить статистику игрока по сезонам.
- Параметры: `playerId` - ID игрока.
- Возвращает: массив ответа API.

```
getPlayerTransferHistory($playerId): array
```
- Назначение: получить историю трансферов.
- Параметры: `playerId` - ID игрока.
- Возвращает: массив ответа API.

```
getPlayerLastMatches($playerId, int $limit = 5): array
```
- Назначение: получить последние матчи игрока.
- Параметры: `playerId` - ID игрока; `limit` - количество матчей.
- Возвращает: массив ответа API.

```
getPlayerProfile(int $playerId): array
```
- Назначение: агрегировать профиль игрока из нескольких эндпоинтов.
- Параметры: `playerId` - ID игрока.
- Возвращает: массив с ключами `detail`, `image`, `characteristics`, `ratings`,
  `statisticsSeasons`, `allStatistics`, `statistics`, `transferHistory`, `lastMatches`, `imageUrl`.
- Особенности: каждый блок оборачивается в try/catch и при ошибке заполняется
  пустым значением; `imageUrl` нормализуется в абсолютный URL, при отсутствии
  формируется фолбэк на `https://api.sofascore.com/api/v1/player/{id}/image`.

```
getTeamDetails(int $teamId): array
```
- Назначение: получить детали команды.
- Параметры: `teamId` - ID команды.
- Возвращает: массив ответа API.

```
searchPlayers(string $query, int $limit = 20): array
```
- Назначение: поиск игроков по строке.
- Параметры: `query` - строка поиска; `limit` - лимит.
- Возвращает: массив DTO `PlayerSofascoreDTO`.
- Особенности: при пустой строке возвращает пустой массив.

```
getPlayersWithFilters(string $query, ?string $country = null, ?string $position = null,
                      ?int $teamId = null, int $limit = 20): array
```
- Назначение: поиск с локальными фильтрами и формирование списков фильтров.
- Параметры: `query`, `country`, `position`, `teamId`, `limit`.
- Возвращает: массив с ключами `players`, `positions`, `countries`.
- Особенности: фильтрация выполняется через `PlayerSofascoreDTO::matchesFilters`.

```
fetch(string $path, array $query = []): array
```
- Назначение: универсальный доступ к любому эндпоинту Sofascore.
- Параметры: `path` - путь; `query` - параметры запроса.
- Возвращает: массив ответа API.

## components/repository/APISofascoreRepository.php
### Класс: APISofascoreRepository
Назначение: низкоуровневый HTTP-клиент для Sofascore (формирование URL, заголовков, разбор ошибок).

#### Свойства
- `private Client $client` - HTTP-клиент Yii.

#### Методы
```
__construct(?Client $client = null)
```
- Назначение: инициализация репозитория.
- Параметры: `client` - внешний клиент; если не передан, используется компонент `sofascore`.

```
request(string $method, string $path, array $data = []): array
```
- Назначение: выполнить HTTP-запрос.
- Параметры: `method`, `path`, `data`.
- Возвращает: декодированный массив ответа.
- Исключения: `RuntimeException` при ошибках HTTP/формата; `HttpClientException`.
- Особенности: собирает абсолютный URL; добавляет заголовки `x-rapidapi-key/host`.

```
get(string $path, array $query = []): array
```
- Назначение: GET-обертка над `request`.
- Параметры: `path`, `query`.
- Возвращает: массив ответа.

```
getClient(): Client
```
- Назначение: доступ к внутреннему клиенту.
- Возвращает: объект `Client`.

## components/DTO/PlayerSofascoreDTO.php
### Класс: PlayerSofascoreDTO
Назначение: DTO для нормализации данных игрока из результатов поиска.

#### Свойства
- `public ?int $id`
- `public ?string $name`
- `public ?string $position`
- `public ?int $teamId`
- `public ?string $teamName`
- `public ?string $country`
- `public array $raw` - исходный ответ.

#### Методы
```
private __construct()
```
- Назначение: запрет прямого создания, используется фабрика.

```
fromApi(array $item): self
```
- Назначение: создать DTO из ответа API.
- Параметры: `item` - элемент поиска.
- Возвращает: заполненный DTO.

```
matchesFilters(string $country = '', string $position = '', int $teamId = 0): bool
```
- Назначение: проверить соответствие фильтрам.
- Параметры: `country`, `position`, `teamId`.
- Возвращает: `true`/`false`.
- Логика: пустые фильтры считаются пройденными.

## controllers/PlayerController.php
### Класс: PlayerController
Назначение: контроллер страниц поиска и профиля игроков.

#### Свойства
- `protected APISofascoreServices $sofascoreService` - сервис API.

#### Методы
```
__construct($id, $module, $config = [], ?APISofascoreServices $sofascoreService = null)
```
- Назначение: внедрение сервиса API и вызов родителя.
- Параметры: стандартные параметры Yii + `sofascoreService`.

```
actionIndex()
```
- Назначение: страница поиска игроков.
- Вход: GET-параметры `q`, `country`, `position`, `teamId`, `limit`.
- Выход: рендер `views/player/index.php` со списками игроков и фильтров.

```
actionView($id)
```
- Назначение: страница профиля игрока (базовые данные).
- Вход: `id` игрока.
- Выход: рендер `views/player/view.php` с `detail` и `imageUrl`.

```
actionProfileData($id)
```
- Назначение: JSON-эндпоинт для полного профиля (AJAX).
- Вход: `id` игрока.
- Выход: JSON `{success: bool, data|error}`.

## controllers/SiteController.php
### Класс: SiteController
Назначение: стандартные страницы сайта (главная, логин, контакт, about) и тест API.

#### Методы
```
behaviors()
```
- Назначение: правила доступа и HTTP-методы (AccessControl, VerbFilter).

```
actions()
```
- Назначение: регистрирует стандартные действия `error` и `captcha`.

```
__construct($id, $module, $config = [], ?APISofascoreServices $sofascoreService = null)
```
- Назначение: внедрение сервиса API; используется в `actionTestApi`.

```
actionIndex()
```
- Назначение: главная страница.
- Выход: `views/site/index.php`.

```
actionLogin()
```
- Назначение: форма авторизации.
- Логика: если пользователь вошел - редирект на главную.
- Выход: `views/site/login.php` или редирект.

```
actionLogout()
```
- Назначение: завершение сессии.
- Выход: редирект на главную.

```
actionContact()
```
- Назначение: форма обратной связи.
- Логика: отправка письма через `ContactForm::contact`.
- Выход: `views/site/contact.php` или refresh.

```
actionAbout()
```
- Назначение: страница "О нас".

```
actionTestApi()
```
- Назначение: тестовый запрос к Sofascore (команда ID=38).
- Выход: HTML-дамп ответа или текст ошибки.

```
actionPlayers()
```
- Назначение: редирект на `player/index` (обратная совместимость).

## models/LoginForm.php
### Класс: LoginForm
Назначение: модель формы логина.

#### Свойства
- `public $username`
- `public $password`
- `public $rememberMe`
- `private $_user` - кэш пользователя.

#### Методы
```
attributeLabels(): array
```
- Назначение: названия полей на русском.

```
rules(): array
```
- Назначение: правила валидации (required, boolean, validatePassword).

```
validatePassword($attribute, $params)
```
- Назначение: проверка пароля через `User::validatePassword`.
- Ошибки: добавляет сообщение "Неверный логин или пароль."

```
login(): bool
```
- Назначение: авторизация пользователя.
- Возвращает: `true` при успехе.

```
getUser(): ?User
```
- Назначение: загрузка пользователя по логину.
- Возвращает: `User` или `null`.

## models/ContactForm.php
### Класс: ContactForm
Назначение: модель формы обратной связи.

#### Свойства
- `public $name`
- `public $email`
- `public $subject`
- `public $body`
- `public $verifyCode`

#### Методы
```
rules(): array
```
- Назначение: правила валидации (required, email, captcha).

```
attributeLabels(): array
```
- Назначение: подписи полей (verifyCode).

```
contact($email): bool
```
- Назначение: отправка письма администратору.
- Параметры: `email` - адрес получателя.
- Возвращает: `true` при успешной отправке.

## models/User.php
### Класс: User
Назначение: модель пользователя и идентификация в системе.

#### Методы
```
tableName(): string
```
- Назначение: имя таблицы БД `{{%user}}`.

```
behaviors(): array
```
- Назначение: поведение `TimestampBehavior` (created_at, updated_at).

```
findIdentity($id)
```
- Назначение: поиск пользователя по ID.

```
findIdentityByAccessToken($token, $type = null)
```
- Назначение: поиск по access_token.

```
findByUsername($username)
```
- Назначение: поиск по логину.

```
getId()
```
- Назначение: вернуть ID пользователя.

```
getAuthKey()
```
- Назначение: вернуть auth_key.

```
validateAuthKey($authKey)
```
- Назначение: проверить auth_key.

```
validatePassword($password)
```
- Назначение: проверить пароль через `Yii::$app->security`.

## widgets/Alert.php
### Класс: Alert
Назначение: отображение flash-сообщений в Bootstrap alert.

#### Свойства
- `public $alertTypes` - соответствие типов flash к Bootstrap классам.
- `public $closeButton` - настройки кнопки закрытия.

#### Методы
```
run()
```
- Назначение: вывести все flash-сообщения и очистить их.
- Выход: HTML-рендер виджета.

## commands/HelloController.php
### Класс: HelloController
Назначение: пример консольной команды.

#### Методы
```
actionIndex($message = 'hello world'): int
```
- Назначение: вывести сообщение в консоль.
- Параметры: `message`.
- Возвращает: `ExitCode::OK`.