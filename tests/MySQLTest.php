<?php declare(strict_types = 1);
use PHPUnit\Framework\TestCase;

final class MySQLTest extends TestCase
{
  public function testSelect(): void
  {
    $this->assertEquals(
      'select users.* from users',
      SuQL::toSql('users {*};', 'mysql')
    );
  }

  public function testSelectFields(): void
  {
    $this->assertEquals(
      'select users.id as uid, users.name as uname from users',
      SuQL::toSql('users {id@uid, name@uname};', 'mysql')
    );
  }

  public function testSelectWhere(): void
  {
    $this->assertEquals(
      "select users.id as uid, users.name as uname from users where users.id > 5 and users.name <> 'admin'",
      SuQL::toSql("
        users {
          id@uid,
          name@uname
        } ~ {uid > 5 and uname <> 'admin'};
      ", 'mysql')
    );
  }

  public function testSelectAllFieldsUsingWhere(): void
  {
    $this->assertEquals(
      "select users.*, users.id as uid from users where users.id > 5",
      SuQL::toSql("
        users {
          *,
          id@uid
        } ~ {uid > 5};
      ", 'mysql')
    );
  }

  public function testGroup(): void
  {
    $this->assertEquals(
      "select groups.name, count(groups.name) as cnt from groups group by groups.name",
      SuQL::toSql("
        groups {
          name,
          name@cnt.group.count
        };
      ", 'mysql')
    );
  }

  public function testJoinGroup(): void
  {
    $this->assertEquals(
      "select ".
        "users.id as u_id, ".
        "user_group.user_id as ug_uid, ".
        "groups.id as g_id, ".
        "groups.name as gname, ".
        "count(groups.name) as cnt ".
      "from users ".
      "inner join user_group on user_group.user_id = users.id ".
      "inner join groups on groups.id = user_group.group_id ".
      "group by groups.name",
      SuQL::toSql("
        users {
          id@u_id
        }

        user_group {
          user_id@ug_uid.join(u_id)
        }

        groups {
          id@g_id.join(user_group.group_id),
          name@gname,
          name@cnt.group.count
        };
      ", 'mysql')
    );
  }

  public function testOrder(): void
  {
    $this->assertEquals(
      "select users.name as uname, users.id as uid from users order by uname desc, uid asc",
      SuQL::toSql("
        users {
          name@uname.desc,
          id@uid.asc
        };
      ", 'mysql')
    );
  }

  public function testSQLObject(): void
  {
    $this->assertEquals(
      [
        'queries' => [
          'main' => [
            'select' => [
              't1.gname' => ['table' => 't1', 'field' => 't1.gname', 'alias' => ''],
              't1.cnt' => ['table' => 't1', 'field' => 't1.cnt', 'alias' => ''],
            ],
            'from' => 't1',
            'where' => [],
            'join' => [],
            'group' => [],
            'order' => [],
            'having' => [],
            'modifier' => null,
            'offset' => null,
            'limit' => null,
          ],
          't1' => [
            'select' => [
              'uid' => ['table' => 'users', 'field' => 'users.id', 'alias' => 'uid'],
              'gid' => ['table' => 'groups', 'field' => 'groups.id', 'alias' => 'gid', 'modifier' => ['join' => ['ug_gid']]],
              'gname' => ['table' => 'groups', 'field' => 'groups.name', 'alias' => 'gname'],
              'cnt' => [
                'table' => 'groups',
                'field' => 'groups.name',
                'alias' => 'cnt',
                'modifier' => [
                  'group' => [
                    0 => 'admin'
                  ],
                  'count' => []
                ],
              ],
              'ug_uid' => [
                'table' => 'user_group',
                'field' => 'user_group.user_id',
                'alias' => 'ug_uid',
                'modifier' => [
                  'join' => ['uid']
                ]
              ],
              'ug_gid' => [
                'table' => 'user_group',
                'field' => 'user_group.group_id',
                'alias' => 'ug_gid',
              ],
            ],
            'from' => 'users',
            'where' => [
              0 => 'uid > 1',
              1 => 'gid > 2',
            ],
            'join' => [
              'user_group' => ['table' => 'user_group', 'on' => ''],
              'groups' => ['table' => 'groups', 'on' => ''],
            ],
            'group' => [],
            'order' => [],
            'having' => [],
            'modifier' => null,
            'offset' => null,
            'limit' => null,
          ]
        ]
      ],
      SuQL::toSqlObject("
        #t1 = users {
          id@uid
        } ~ {uid > 1}

        user_group {
          user_id@ug_uid.join(uid),
          group_id@ug_gid
        }

        groups {
          id@gid.join(ug_gid),
          name@gname,
          name@cnt.group(admin).count
        } ~ {gid > 2};

        t1 {
          gname,
          cnt
        };
      ", 'mysql', 'beforePreparing')
    );
  }

  public function testNestedQuery(): void
  {
    $this->assertEquals(
      'select '.
        't1.gname, '.
        't1.cnt '.
      'from ('.
        'select '.
          'users.id as uid, '.
          'user_group.user_id as ug_uid, '.
          'user_group.group_id as ug_gid, '.
          'groups.id as gid, '.
          'groups.name as gname, '.
          'count(groups.name) as cnt '.
        'from users '.
        'inner join user_group on user_group.user_id = users.id '.
        'inner join groups on groups.id = user_group.group_id '.
        'where users.id > 1 and groups.id > 2 '.
        'group by groups.name'.
      ') t1',
      SuQL::toSql("
        #t1 = users {
          id@uid
        } ~ {uid > 1}

        user_group {
          user_id@ug_uid.join(uid),
          group_id@ug_gid
        }

        groups {
          id@gid.join(ug_gid),
          name@gname,
          name@cnt.group.count
        } ~ {gid > 2};

        t1 {
          gname,
          cnt
        };
      ", 'mysql')
    );
  }

  public function testHaving(): void
  {
    $this->assertEquals(
      "select users.id as uid, user_group.user_id as ug_uid, groups.id as gid, groups.name as uname, count(groups.name) as cnt from users inner join user_group on user_group.user_id = users.id inner join groups on groups.id = user_group.group_id group by groups.name having uname = 'admin'",
      SuQL::toSql("
        users {
          id@uid
        }

        user_group {
          user_id@ug_uid.join(uid)
        }

        groups {
          id@gid.join(user_group.group_id),
          name@uname.group('admin'),
          name@cnt.count
        };
      ", 'mysql')
    );
  }

  public function testJoin(): void
  {
    $this->assertEquals(
      "select table1.*, table2.Id as t2id, table3.id as t3id from table1 left join table2 on table2.Id = table1.id right join table3 on table3.id = table1.id",
      SuQL::toSql("
        table1 {
          *
        }

        table2 {
          Id@t2id.left_join(table1.id)
        }

        table3 {
          id@t3id.right_join(table1.id)
        };
      ", 'mysql')
    );
  }

  public function testFieldsWithNoAliases(): void
  {
    $this->assertEquals(
      'select '.
        'users.*, '.
        'user_group.user_id, '.
        'groups.*, '.
        'groups.id '.
      'from users '.
      'inner join user_group on user_group.user_id = users.id '.
      'inner join groups on groups.id = user_group.group_id',
      SuQL::toSql("
        users {*}

        user_group {
          user_id.join(users.id)
        }

        groups {
          *,
          id.join(user_group.group_id)
        };
      ", 'mysql')
    );
  }

  public function testSuQLWordsToSQL(): void
  {
    $this->assertEquals(
      ['word1', 'word2', 'now()', 'word3'],
      SuQLReservedWords::toSql(['word1', 'word2', 'now', 'word3'])
    );
  }

  public function testWhereBitwise(): void
  {
    $this->assertEquals(
      "select users.* from users where users.id % 2 = 0",
      SuQL::toSql("
        users {
          *
        } ~ {users.id % 2 = 0};
      ", 'mysql')
    );
  }

  public function testOrderDataInCountColumn(): void {
    $this->assertEquals(
      "select user_group.user_id, groups.id, groups.name as gname, count(groups.name) as count ".
      "from users ".
      "inner join user_group on user_group.user_id = users.id ".
      "inner join groups on groups.id = user_group.group_id ".
      "group by groups.name ".
      "order by count asc",
      SuQL::toSql("
        users {}

        user_group {
          user_id.join(users.id)
        }

        groups {
          id.join(user_group.group_id),
          name@gname,
          name@count.group.count.asc
        };
      ", 'mysql')
    );
  }

  public function testLimit(): void {
    $this->assertEquals(
      "select users.* from users limit 30",
      SuQL::toSql("
        users {
          *
        } [30];
      ", 'mysql')
    );
  }

  public function testOffsetLimit(): void {
    $this->assertEquals(
      "select users.* from users limit 30, 30",
      SuQL::toSql("
        users {*} [ 30 , 30 ]
      ", 'mysql')
    );
  }

  public function testWhereAndLimitTogether(): void {
    $this->assertEquals(
      "select users.* from users where users.id % 2 = 0 limit 0, 1",
      SuQL::toSql("
        users {*} ~ {users.id % 2 = 0} [0, 1];
      ", 'mysql')
    );
  }

  public function testObjectSelectModifier(): void {
    $this->assertEquals(
      [
        'queries' => [
          'main' => [
            'select' => [
              'users.*' => [
                'table' => 'users',
                'field' => 'users.*',
                'alias' => ''
              ]
            ],
            'from' => 'users',
            'where' => [],
            'having' => [],
            'join' => [],
            'group' => [],
            'order' => [],
            'modifier' => 'distinct',
            'offset' => null,
            'limit' => null,
          ]
        ]
      ],
      SuQL::toSqlObject("
        users.distinct {*};
      ", 'mysql', 'beforePreparing')
    );
  }

  public function testSelectModifier(): void {
    $this->assertEquals(
      "select distinct users.id, users.name from users",
      SuQL::toSql("
        users.distinct {id, name};
      ", 'mysql')
    );
  }

  public function testNestedQueryInWhereClause(): void {
    $this->assertEquals(
      "select ".
        "users.name ".
      "from users ".
      "where users.id not in (select distinct user_group.user_id from user_group)",
      SuQL::toSql("
        #users_belong_to_any_group = user_group.distinct {user_id};

        users {
          name
        } ~ {users.id not in #users_belong_to_any_group};
      ", 'mysql')
    );
  }

}
