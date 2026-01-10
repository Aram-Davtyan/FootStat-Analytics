# Документация ИС: информационное обеспечение, модели, алгоритмы, ПО и контроль версий

## 1. Назначение ИС
ИС предназначена для поиска футболистов и отображения профиля игрока с данными из Sofascore
(детали, характеристики, рейтинги, статистика, трансферы, последние матчи).

## 2. Информационное обеспечение
### 2.1. Источники данных
- Внешний источник: Sofascore API (через RapidAPI).
- Каналы: HTTP(S) запросы из сервера приложения.
- Кэш/БД: в текущей реализации отсутствует; данные агрегируются на лету.

### 2.2. Входные данные
- `q` (строка): поисковый запрос (имя/фамилия).
- `country` (строка, опционально): фильтр по стране.
- `position` (строка, опционально): фильтр по позиции.
- `teamId` (число, опционально): фильтр по команде.
- `id` (число): идентификатор игрока.

### 2.3. Выходные данные
- Список игроков с метаданными (поиск).
- Профиль игрока: детали, характеристики, рейтинги, статистика, трансферы, последние матчи.
- Изображение игрока (через серверный прокси).

### 2.4. Логическая структура данных
- Player: `id`, `name`, `shortName`, `country`, `position`, `age`, `height`, `weight`, `team`.
- Team: `id`, `name`, `shortName`.
- Characteristics: список пар `{name, value}`.
- Ratings: список пар `{type, value}`.
- Statistics: список записей по сезону/турниру.
- Transfer: `{date, fromTeam, toTeam, fee}`.
- Match: `{startTimestamp, homeScore, awayScore, opponent, rating}`.

## 3. Модели и алгоритмы ИС
### 3.1. Архитектурная модель (MVC)
- Контроллеры: `controllers/PlayerController.php`.
- Сервисы: `components/services/APISofascoreServices.php`.
- Репозиторий: `components/repository/APISofascoreRepository.php`.
- Представления: `views/player/index.php`, `views/player/view.php`.

### 3.2. Алгоритм поиска игроков
1. Пользователь вводит `q` и (опционально) фильтры.
2. Контроллер вызывает `APISofascoreServices::getPlayersWithFilters`.
3. Сервис вызывает API поиска, фильтрует результаты по стране/позиции/команде.
4. Возвращается список игроков и списки доступных фильтров.

### 3.3. Алгоритм формирования профиля игрока
1. Получить базовые детали игрока (`getPlayerDetails`).
2. Параллельно/последовательно запросить:
   - изображение (`getPlayerImage`);
   - характеристики, рейтинги, статистику, трансферы, последние матчи.
3. Объединить ответы в единый профиль.
4. Если изображение недоступно, использовать прокси-эндпоинт.

### 3.4. Алгоритм получения изображения
1. Клиентский браузер запрашивает `/player/image?id={id}`.
2. Сервер делает запрос к `https://api.sofascore.com/api/v1/player/{id}/image`
   с заголовками `User-Agent/Accept/Referer`.
3. Сервер возвращает изображение с корректным `Content-Type`.

## 4. Математическое описание (формализация)
Пусть:
- `P` - множество игроков, `T` - множество команд, `C` - множество стран, `S` - множество позиций.
- `q` - поисковая строка.
- `country` ∈ `C` ∪ {`∅`}, `position` ∈ `S` ∪ {`∅`}, `teamId` ∈ `T` ∪ {`∅`}.

Функция поиска:
- `Search(q)` возвращает множество `P_q ⊆ P`.

Функция фильтрации:
- `Filter(P_q, country, position, teamId) = { p ∈ P_q | f_c(p)=country ∧ f_s(p)=position ∧ f_t(p)=teamId }`,
  где условия с `∅` трактуются как истинные.

Функция профиля:
- `Profile(p) = ⟨Details(p), Image(p), Characteristics(p), Ratings(p), Statistics(p), Transfers(p), Matches(p)⟩`.

Итоговый результат:
- `Result = Profile(p)` для выбранного `p ∈ P`.

## 5. Схемное представление (ASCII)
### 5.1. Контекстная схема
```
Пользователь -> Web UI -> Контроллер -> Сервис -> Репозиторий -> Sofascore API
                                      <- ответы <-
```

### 5.2. Компонентная схема
```
views/player/*  ->  controllers/PlayerController
                          |
                          v
           components/services/APISofascoreServices
                          |
                          v
          components/repository/APISofascoreRepository
                          |
                          v
                   Sofascore API
```

### 5.3. Диаграмма последовательности (профиль)
```
Browser -> PlayerController.actionView -> APISofascoreServices.getPlayerDetails
Browser -> PlayerController.actionProfileData -> APISofascoreServices.getPlayerProfile
APISofascoreServices -> APISofascoreRepository.get -> Sofascore API
Sofascore API -> APISofascoreRepository -> APISofascoreServices -> Browser
```

## 6. Программное обеспечение ИС
### 6.1. Технологический стек
- PHP 7.4+
- Yii 2 (basic)
- yii\httpclient\Client
- Bootstrap 5 (frontend)

### 6.2. Ключевые модули
- API клиент и репозиторий: `components/repository/APISofascoreRepository.php`
- Бизнес-логика: `components/services/APISofascoreServices.php`
- Контроллеры: `controllers/PlayerController.php`
- Представления: `views/player/index.php`, `views/player/view.php`

### 6.3. Внешние зависимости
- `X_RAPIDAPI_KEY`, `X_RAPIDAPI_HOST` для доступа к Sofascore.

## 7. Реализация в условиях версионного контроля
### 7.1. Практика Git
- Основная ветка: `main`.
- Ветви задач: `feature/sofascore-<topic>`, `fix/<issue>`.
- Коммиты: короткое описание, например `feat: add player profile aggregation`.
- Pull request перед слиянием (желательно).

### 7.2. Артефакты и воспроизводимость
- Конфигурации в `config/*`.
- Зависимости в `composer.json`/`composer.lock`.
- Документация в `docs/IS_DOCUMENTATION.md`.

### 7.3. Тестирование
- Запуск тестов: `vendor/bin/codecept run` (если настроено окружение).
