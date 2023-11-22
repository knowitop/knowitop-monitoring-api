# Модуль для интеграции iTop с системами мониторинга

Что умеет модуль:

1. создавать инциденты при возникновении аварий, назначать команду и агента;
1. привязывать аварийные конфигурационные единицы к инцидентам;
1. выполнять инциденты при восстановлении и возвращать в работу при повторении аварии;
1. добавлять сообщения в журнал инцидента.

Модуль представляет собой расширение REST API iTop. Логика работы модуля полностью настраиваемая, правила обработки событий определяются в конфигурационном файле. Для различных групп мониторинга можно настроить разные правила обработки событий.

## Общие сведения

Модуль добавляет в [HTTP API iTop](https://www.itophub.io/wiki/page?id=2_6_0%3Aadvancedtopics%3Arest_json) новый метод, с помощью которого можно выполнить несколько действий одним HTTP-запросом из системы мониторинга, например создать инцидент, привязать КЕ, назначить на нужного агента и написать сообщение в журнал.

Основные задачи модуля:
 - упростить настройку и поддержку систем мониторинга в части отправки событий в iTop;
 - свести к минимуму количество разнообразных скриптов для отправки запросов;
 - вернуть все специфичные данные из скриптов обратно в iTop (услуги, команды, агенты и т.п.).

## Установка

Установка выполняется по стандартной процедуре. Модуль не изменяет модель данных iTop и после тестирования может быть удален из системы без негативных последствий.

### Требования

PHP 7, iTop 2.6 и выше (на более ранних версиях iTop работа не проверялась), модули Simple Ticket Management или Incident Management.

## Передача информации о событии мониторинга в iTop

Пример запроса (что нужно передать в параметре `json_data`):

```json
{
  "operation": "monitoring/alarm",
  "comment": "Наш Zabbix",
  "context": "zabbix_servers",
  "ci_key": "Server2 (Grenoble)",
  "state": true,
  "message": "PROBLEM: host Server2 (Grenoble) unavailable",
  "output_fields": "ref,title,status,team_id_friendlyname,agent_id_friendlyname,functionalcis_list"
}
```

В зависимости от настроек модуля данный запрос может приводить к различным действиям в iTop.

### Параметры запроса

#### operation
Значение `monitoring/alarm` – единственная доступная в настоящее время операция.

#### comment 
Комментарий, который будет указан в истории создаваемого/изменяемого тикета.

#### context
Название контекста обработки события мониторинга, в котором задаются правила обработки (см. параметры модуля).

#### ci_key
Идентификатор, который будет использоваться для поиска соответствующей КЕ в iTop.

#### state
Состояние события мониторинга: `true` или `1` – авария началась, `false` или `0` – авария закончилась.

#### message
Текстовое сообщение события мониторинга.

#### output_fields
Атрибуты тикета, которые нужно вернуть в ответе на HTTP-запрос (см. [описание стандартного API iTop](https://www.itophub.io/wiki/page?id=2_6_0%3Aadvancedtopics%3Arest_json#response)).

## Настройка модуля

При поступлении HTTP-запроса айтоп последовательно:
1. с помощью `ci_oql` ищет КЕ, которая сгенерировала событие мониторинга;
2. с помощью `ticket_oql` ищет тикет, относящийся к данному событию;
3. выполняет определенные действия (создать, назначить, написать в журнал и т.д.).

Блок настроек модуля разбивается на контексты обработки событий. Внутри каждого контекста задаются собственные правила обработки события.

Пример конфигурации модуля с одним контекстом `zabbix_servers`:

```php
'knowitop-monitoring-api' => array(
    'zabbix_servers' => array(
        'ci_oql' => 'SELECT Server WHERE name = :alarm->ci_key AND status = \'production\'',
        'ticket_oql' => 'SELECT UserRequest AS i JOIN lnkFunctionalCIToTicket AS lnk ON lnk.ticket_id = i.id JOIN Server AS ci ON lnk.functionalci_id = ci.id WHERE ci.name = :alarm->ci_key AND i.request_type = \'incident\' AND i.status NOT IN (\'closed\')',
        'actions_on_problem' => array(
            'create' => array(
                'fields' => array(
                    'org_id' => array('name' => '$ci->org_id->name$'),
                    'caller_id' => 'SELECT Person WHERE org_id = $ci->org_id$ AND id = 2',
                    'title' => 'Авария: $alarm->message$',
                    'description' => 'Авария: $alarm->message$ на КЕ $ci->name$',
                    'service_id' => 2,
                ),
            ),
            'assign' => array(
                'fields' => array(
                    'team_id' => 'SELECT Team AS t JOIN lnkContactToFunctionalCI AS lnk1 ON lnk1.contact_id = t.id JOIN Server AS ci ON lnk1.functionalci_id = ci.id WHERE ci.id = $ci->id$',
                    'agent_id' => 10,
                    'private_log' => 'Авария: $alarm->message$',
                ),
            ),
            'reopen',
            'write_to_log' => array(
                'log_att_code' => 'private_log',
                'text' => 'Появилась авария: $alarm->message$',
            ),
        ),
        'actions_on_ok' => array(
            'resolve' => array(
                'fields' => array(
                    'resolution_code' => 'other',
                    'solution' => 'Работа КЕ $ci->name$ восстановлена.',
                ),
            ),
            'write_to_log' => array(
                'log_att_code' => 'private_log',
                'text' => 'Завершилась авария: $alarm->message$',
            ),
        ),
    ),
),
```

### Параметры контекста

#### ci_oql

OQL-запрос для поиска КЕ. Найденная КЕ будет автоматически связана с создаваемым тикетом. В запросе можно использовать следующие параметры: `alarm->ci_key`, `alarm->state`, `alarm->message`.

Пример: `SELECT Server WHERE name = :alarm->ci_key AND status = 'production'`.

#### ci_key_regexp

Регулярное выражение для преобразования переданного в HTTP-запросе параметра `ci_key`. Может быть полезно, если идентификатор КЕ в айтопе и в системе мониторинга совпадают частично. В регулярном выражении должна быть определена скобочная группа, совпадение с которой будет помещено в параметр `alarm->ci_key`.

Пример: `/(Server\\d+)/`. Если из системы мониторинга придет `My super Server2 (Grenoble)`, в `alarm->ci_key` попадёт `Server2`.

#### ticket_oql

OQL-запрос для поиска тикета. Найденный тикет будет считаться относящимся к поступившему событию мониторинга. В запросе можно использовать следующие параметры: `alarm->ci_key`, `alarm->state`, `alarm->message`, `ci->att_code` (где att_code – атрибут найденной КЕ).

Пример: `SELECT Incident AS i JOIN lnkFunctionalCIToTicket AS lnk ON lnk.ticket_id = i.id JOIN Server AS ci ON lnk.functionalci_id = ci.id WHERE ci.name = :alarm->ci_key AND i.status != 'closed'`.

#### actions_on_problem

Список действий, которые будут выполнены над тикетом при поступлении события начала аварии (`"state": 1`). Доступные действия: `create`, `assign`, `reopen`, `write_to_log`. См. описание действий ниже.

#### actions_on_ok

Список действий, которые будут выполнены над тикетом при поступлении события окончания аварии (`"state": 0`). Доступные действия: `resolve`, `write_to_log`. См. описание действий ниже.

### Действия над тикетом

Последовательность выполнения действий фиксированная, если какое-то из определенных действий неприменимо к тикету, оно пропускается. Например, для `actions_on_problem` заданы следующие действия `create`, `assign`, `reopen`, `write_to_log`. Если `ticket_oql` не вернул тикет, айтоп создаст новый тикет, затем назначит его и напишет сообщение в журнал. Действие `reopen` будет проигнорировано. Если `ticket_oql` вернул тикет в статусе _Выполнен_, айтоп вернет тикет в работу и напишет сообщение в журнал. Действия `create` и `assign` в данном случае будут проигнорированы.

#### create

Создать новый тикет, если запрос `ticket_oql` не нашел существующий тикет. В параметре `fields` должны быть переданы атрибуты создаваемого тикета. Внутри значений `fields` можно использовать заменители (плейсхолдеры): `$alarm->ci_key$`, `$alarm->state$`, `$alarm->message$`, `$ci->att_code$` (где att_code – атрибут найденной КЕ).

Пример:
```php
'create' => array(
    'fields' => array(
        'org_id' => array('name' => '$ci->org_id->name$'),
        'caller_id' => 'SELECT Person WHERE org_id = $ci->org_id$ AND id = 2',
        'title' => 'Авария: $alarm->message$',
        'description' => 'Авария: $alarm->message$ на КЕ $ci->name$',
        'service_id' => 2,
    ),
),
```

#### assign

Назначить созданный или найденный тикет. Параметры аналогичны действию `create`, к упомянутым выше заменителям добавляется `$ticket->att_code$`, где att_code – атрибут назначаемого тикета.

```php
'assign' => array(
    'fields' => array(
        'team_id' => 'SELECT Team AS t JOIN lnkContactToService AS lnk ON lnk.contact_id = t.id JOIN Service AS s ON lnk.service_id = s.id WHERE s.id = $ticket->service_id$',
        'agent_id' => 10,
        'private_log' => 'Авария: $alarm->message$',
    ),
),
```

#### resolve

Выполнить назначенный тикет. Параметры аналогичны действию `assign`.

#### reopen

Вновь открыть выполненный тикет. Параметры аналогичны действию `assign`.

#### write_to_log

Добавить сообщение в журнал тикета. В параметрах указывается атрибут журнала (по умолчанию `private_log`) и тест сообщений (по умолчанию `$alarm->message$`). Все описанные выше заменители также доступны.

Пример:
```php
'write_to_log' => array(
    'log_att_code' => 'private_log',
    'text' => 'Авария $alarm->message$ на КЕ $ci->name$',
),
```

## Ограничения
- Тикеты только Incident или UserRequest.
- Каждое действие – отдельная операция обновления. Если в процессе выполнения последовательности действий происходит ошибка, откат к начальному состоянию не производится, и выполненные успешно действия не отменяются.

## Примеры конфигов

Пример POST-запроса, который должна отправить система мониторинга при возникновении аварии:
```
POST http://localhost:8000/webservices/rest.php?version=1.4
Authorization: Basic cmVzdDpyZXN0
Content-Type: application/x-www-form-urlencoded

json_data={
  "operation": "monitoring/alarm",
  "comment": "Monitoring System",
  "context": "demo_context",
  "ci_key": "Server4",
  "state": 1,
  "message": "Server4 is down",
  "output_fields": "title,status,team_id_friendlyname,agent_id_friendlyname,solution"
}
```
Тот же запрос через cURL:
```
curl -X POST --location "http://localhost:8000/webservices/rest.php?version=1.4" \
    -H "Authorization: Basic cmVzdDpyZXN0" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "json_data={
  \"operation\": \"monitoring/alarm\",
  \"comment\": \"Monitoring System\",
  \"context\": \"demo_context\",
  \"ci_key\": \"Server4\",
  \"state\": 1,
  \"message\": \"Server4 is down\",
  \"output_fields\": \"title,status,team_id_friendlyname,agent_id_friendlyname,solution\"
}"
```

### Интеграция с Zabbix

Используя файл examples/zabbix/media_itop.yaml, который является модифицированной версией конфигурационного файла вот с этого источника:
https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/media/itop/media_itop.yaml
можно легко интегрировать данный модуль с системой мониторинга Zabbix следуя вот этой инструкции:
https://www.zabbix.com/integrations/itop

