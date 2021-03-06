<p align="center">
  <img src="/assets/images/logo.png" alt="logo"/>
</p>

<p align="center">
  <a href="README.md">
    <img src="/assets/images/en.png" alt="Read SuQL documentation in English"/>
  </a>
  <a href="README.ru.md">
    <img src="/assets/images/ru.png" alt="Читать SuQL документация на русском"/>
  </a>
</p>

# Sugar SQL

<p align="left">
  <img src="https://img.shields.io/github/v/release/sagittaracc/suql" alt="GitHub release (latest by date)"/>
  <a href="https://github.com/sagittaracc/suql/blob/master/LICENSE">
    <img src="https://img.shields.io/github/license/sagittaracc/suql" alt="GitHub license"/>
  </a>
  <a href="https://github.com/sagittaracc/suql/issues">
    <img src="https://img.shields.io/github/issues/sagittaracc/suql" alt="GitHub issues"/>
  </a>
</p>

### What is this?
SuQL is syntactic sugar for SQL used by [SuQL Script](https://github.com/sagittaracc/suql-script).

### Why do you need this?
1. Write queries once, use for every DBMS.
2. Write queries that are easy to read and write.
3. Expand SuQL syntax on your own. SQL isn't the limit. There's no limit really.

### How do you use this?
1. Install via composer ```composer require sagittaracc/suql```
2. Write an example ```test.php```
```php
<?php
require 'vendor/autoload.php';
$suql = (new SuQL)->setAdapter('mysql');
$sql = $suql->query('
  SELECT FROM users
    id,
    name
  ;
')->getSQL();
echo $sql;
```
3. Run the test
```
> php test.php
select users.id, users.name from users
```


### Demo
See all the examples in the ```tests``` folder and try it [here](https://repl.it/@sagittaracc/suql-example)

# Documentation

### Sample Database

![Sugar SQL Sample Database](/assets/images/Sugar-SQL-Sample-Database.png)



# SuQL Syntax
## Querying Data

**Sugar SQL approach:**
```sql
SELECT FROM users
  id,
  name
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->select()
                      ->users()
                        ->field('id')
                        ->field('name');
```
|id   |name   |
|---|---|
|1   |Yuriy   |
|2   |Alex   |
|3   |Vlad   |
|4   |Den   |

**Sugar SQL approach**
```sql
SELECT FROM users
  *
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->select()
                      ->users();
```

**Sugar SQL approach**
```sql
SELECT FROM users
  id@uid,
  name@uname
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->select()
                      ->users()
                        ->field(['id' => 'uid'])
                        ->field(['name' => 'uname']);
```
|uid   |uname   |
|---|---|
|1   |Yuriy   |
|2   |Alex   |
|3   |Vlad   |
|4   |Den   |



## Filtering Data
> Example: Get all the users with even id's

**Sugar SQL approach**
```sql
SELECT FROM users
  id@uid,
  name@uname
WHERE uid % 2 = 0
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->select()
                      ->users()
                        ->field(['id' => 'uid'])
                        ->field(['name' => 'uname'])
                      ->where('uid % 2 = 0');
```
|uid   |uname   |
|---|---|
|2   |Alex   |
|4   |Den   |

> Example: Get all the users who do not belong to any groups

**Sugar SQL approach**
```sql
@users_belong_to_any_group = SELECT DISTINCT FROM user_group
                              user_id
                             ;
SELECT FROM users
  id@uid,
  name
WHERE uid not in @users_belong_to_any_group
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->query('users_belong_to_any_group')
                      ->select()
                        ->user_group('distinct')
                          ->field('user_id');
$osuql->query()
        ->select()
          ->users()
            ->field(['id' => 'uid'])
            ->field('name')
        ->where('uid not in @users_belong_to_any_group');
```

> Example: Get the first two users

**Sugar SQL approach**
```sql
SELECT FROM users
  *
LIMIT 0, 2
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->select()
                      ->users()
                        ->field('*')
                    ->offset(0)
                    ->limit(2);
```
| id | name  | registration        |
|----|-------|---------------------|
| 1  | Yuriy | 2019-12-10 10:03:16 |
| 2  | Alex  | 2020-04-08 10:03:16 |

> Example: Get uniques users names

```sql
SELECT DISTINCT FROM users
  name
;
```


## Joining Multiple Tables
> Example: Link all three tables together to see how many admins we have.

**Sugar SQL approach**
```sql
SELECT FROM users
INNER JOIN user_group
INNER JOIN groups
  id@gid,
  name@gname
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->rel(['users' => 'u'], ['user_group' => 'ug'], 'u.id = ug.user_id')
                    ->rel(['user_group' => 'ug'], ['groups' => 'g'], 'ug.group_id = g.id');

$osuql->select()
        ->users()
        ->user_group()
        ->groups()
          ->field(['name' => 'gname'])
```
|gname   |
|---|
|admin   |
|admin   |
|admin   |
|user   |



## Grouping Data
> Example: How many admins? Use the count modifier to calc the exact number.

**Sugar SQL approach**
```sql
SELECT FROM users
INNER JOIN user_group
INNER JOIN groups
  name@gname,
  name.group.count@count
WHERE gname = 'admin'
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->rel(['users' => 'u'], ['user_group' => 'ug'], 'u.id = ug.user_id')
                    ->rel(['user_group' => 'ug'], ['groups' => 'g'], 'ug.group_id = g.id');

$osuql->select()
        ->users()
        ->user_group()
        ->groups()
          ->field(['name' => 'gname'])
          ->field(['name' => 'count'])->group()->count()
        ->where("gname = 'admin'");
```
|gname   |count   |
|---|---|
|admin   |3   |



## Nested Queries

**Sugar SQL approach**
```sql
@allGroupsCount = SELECT FROM users
                  INNER JOIN user_group
                  INNER JOIN groups
                    name@gname,
                    name.group.count@count
                  ;
SELECT FROM allGroupsCount
  gname,
  count
WHERE gname = 'admin'
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->rel(['users' => 'u'], ['user_group' => 'ug'], 'u.id = ug.user_id')
                    ->rel(['user_group' => 'ug'], ['groups' => 'g'], 'ug.group_id = g.id');

$osuql->query('allGroupsCount')
        ->select()
          ->users()
          ->user_group()
          ->groups()
            ->field(['name' => 'gname'])
            ->field(['name' => 'count'])->group()->count();

$osuql->query()
        ->select()
          ->allGroupsCount()
            ->field('gname')
            ->field('count')
          ->where("gname = 'admin'");
```
|gname   |count   |
|---|---|
|admin   |3   |



## Sorting Data

**Sugar SQL approach**
```sql
SELECT FROM users
INNER JOIN user_group
INNER JOIN groups
  name@gname,
  name.group.count.asc@count
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->rel(['users' => 'u'], ['user_group' => 'ug'], 'u.id = ug.user_id')
                     ->rel(['user_group' => 'ug'], ['groups' => 'g'], 'ug.group_id = g.id');

$osuql->select()
        ->users()
        ->user_group()
        ->groups()
          ->field(['name' => 'gname'])
          ->field(['name' => 'count'])->group()->count()->asc();
```
| user_id | id | gname | count |
|---------|----|-------|-------|
| 4       | 2  | user  | 1     |
| 1       | 1  | admin | 3     |



## UNION

**Sugar SQL approach**
```sql
@firstRegisration = SELECT FROM users
                      registration.min@reg_interval
                    ;
@lastRegisration = SELECT FROM users
                     registration.max@reg_interval
                   ;

@main = @firstRegisration union @lastRegisration;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->rel(['users' => 'u'], ['user_group' => 'ug'], 'u.id = ug.user_id')
                    ->rel(['user_group' => 'ug'], ['groups' => 'g'], 'ug.group_id = g.id');

$osuql->query('firstRegisration')
        ->select()
          ->users()
            ->field('registration@reg_interval')->min()
       ->query('lastRegisration')
        ->select()
          ->users()
            ->field('registration@reg_interval')->max()
       ->query()
        ->union('@firstRegisration')
        ->union('@lastRegisration');
```
| reg_interval |
|---------|
| 2019-06-12 10:03:16 |
| 2020-04-21 21:16:23 |



## CASE Expression
You can create SQL CASE Expressions by using custom modifiers.



## Modifiers
To develop your own modifiers:
1. Include `dist/suql.phar` in your project
2. Define the `SQLExtModifier` class that has to be extended from the `SQLBaseModifier` class.
3. Define a public static function with the `mod_` prefix and then the name of the modifier.

> Example: Define a standart SQL function `min`

```php
class SQLExtModifier extends SQLBaseModifier
{
  public static function mod_min($ofield, $params) {
    parent::default_handler('min', $ofield, $params);
  }
}
```
> Example: When has the first user registered?

```sql
SELECT FROM users
  registration.min@firstReg
;
```
| firstReg            |
|---------------------|
| 2019-06-12 10:03:16 |



### CASE Expression as a custom modifier
> Example: Show groups permissions depends on the group name.

```php
class SQLExtModifier extends SQLBaseModifier
{
  // ...
  public static function mod_permission($ofield, $params) {
    parent::mod_case([
      "$ = 'admin'" => 'can do everything',
      "$ = 'user'"  => 'can read only',
      'default'     => 'can do nothing',
    ], $ofield, $params);
  }
  // ...
}
```

**Sugar SQL approach**
```sql
SELECT FROM groups
  id,
  name,
  name.permission@permission
;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->query()
                      ->groups()
                        ->field('id')
                        ->field('name')
                        ->field(['name' => 'permission'])->permission();
```
| id | name  | permission        |
|----|-------|-------------------|
| 1  | admin | can do everything |
| 2  | user  | can read only     |
| 3  | guest | can do nothing    |



## COMMANDS
You can run different commands as PHP functions.

To develop your own commands:
1. Include `dist/suql.phar` in your project
2. Define the `SuQLExtCommand` class that has to be extended from the `SuQLBaseCommand` class.
3. Define a public static function that is going to be used as the command name;

> Example: Show if all the admins were created in the same month

```php
class SuQLExtCommand extends SuQLBaseCommand {
  // ...
  public static function isSameMonth($userList) {
    $month = null;

    foreach ($userList as $user) {
      if (is_null($month))
        $month = date("m", strtotime($user['registration']));
      else if ($month !== date("m", strtotime($user['registration'])))
        return false;
    }

    return true;
  }
  // ...
}
```

**Sugar SQL approach**
```sql
@allTheAdmins = SELECT FROM users
                  id,
                  registration
                INNER JOIN user_group
                INNER JOIN groups
                  name@group
                WHERE group = \'admin\';

@main = %isSameMonth @allTheAdmins;
```

**Object Oriented approach**
```php
$osuql = (new OSuQL)->query('allTheAdmins')
                      ->select()
                        ->users()
                          ->field('id')
                          ->field('registration')
                        ->user_group()
                        ->groups()
                          ->field(['name' => 'group'])
                      ->where("group = 'admin'")
                     ->query()
                      ->command('isSameMonth', ['allTheAdmins']);
```



## Conclusion

SuQL is all about modifiers and commands. Modifiers already replace standart SQL clauses such as `WHERE`, `GROUP`, `JOIN`, `ORDER` and SQL functions etc. Meanwhile commands can do everything that SuQL or SQL can\'t.
More than that, you can develop your own modifiers and commands.
