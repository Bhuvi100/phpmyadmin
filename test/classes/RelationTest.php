<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests;

use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Relation;

/**
 * @group medium
 */
class RelationTest extends AbstractTestCase
{
    /** @var Relation */
    private $relation;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp(): void
    {
        parent::setUp();
        parent::defineVersionConstants();
        parent::setTheme();
        $GLOBALS['server'] = 1;
        $GLOBALS['db'] = 'db';
        $GLOBALS['cfg']['Server']['user'] = 'root';
        $GLOBALS['cfg']['Server']['pmadb'] = 'phpmyadmin';
        $GLOBALS['cfg']['Server']['DisableIS'] = false;
        $GLOBALS['cfg']['ZeroConf'] = true;
        $_SESSION['relation'][$GLOBALS['server']] = 'PMA_relation';
        $_SESSION['relation'] = [];

        $GLOBALS['cfg']['ServerDefault'] = 0;

        $this->relation = new Relation($GLOBALS['dbi']);
    }

    /**
     * Test for queryAsControlUser
     */
    public function testPMAQueryAsControlUser(): void
    {
        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dbi->expects($this->once())
            ->method('query')
            ->will($this->returnValue('executeResult1'));

        $dbi->expects($this->once())
            ->method('tryQuery')
            ->will($this->returnValue('executeResult2'));

        $GLOBALS['dbi'] = $dbi;
        $this->relation->dbi = $GLOBALS['dbi'];

        $sql = 'insert into PMA_bookmark A,B values(1, 2)';
        $this->assertEquals(
            'executeResult1',
            $this->relation->queryAsControlUser($sql)
        );
        $this->assertEquals(
            'executeResult2',
            $this->relation->queryAsControlUser($sql, false)
        );
    }

    /**
     * Test for getRelationsParam & getRelationsParamDiagnostic
     */
    public function testPMAGetRelationsParam(): void
    {
        $relationsPara = $this->relation->getRelationsParam();
        $this->assertFalse(
            $relationsPara['relwork']
        );
        $this->assertFalse(
            $relationsPara['bookmarkwork']
        );
        $this->assertEquals(
            'root',
            $relationsPara['user']
        );
        $this->assertEquals(
            'phpmyadmin',
            $relationsPara['db']
        );

        $retval = $this->relation->getRelationsParamDiagnostic($relationsPara);
        //check $cfg['Servers'][$i]['pmadb']
        $this->assertStringContainsString(
            "\$cfg['Servers'][\$i]['pmadb']",
            $retval
        );
        $this->assertStringContainsString(
            '<strong>OK</strong>',
            $retval
        );

        //$cfg['Servers'][$i]['relation']
        $result = "\$cfg['Servers'][\$i]['pmadb']  ... </th><td class=\"right\">"
            . '<span class="success"><strong>OK</strong></span>';
        $this->assertStringContainsString(
            $result,
            $retval
        );
        // $cfg['Servers'][$i]['relation']
        $result = "\$cfg['Servers'][\$i]['relation']  ... </th><td class=\"right\">"
            . '<span class="caution"><strong>not OK</strong></span>';
        $this->assertStringContainsString(
            $result,
            $retval
        );
        // General relation features
        $result = 'General relation features: <span class="caution">Disabled</span>';
        $this->assertStringContainsString(
            $result,
            $retval
        );
        // $cfg['Servers'][$i]['table_info']
        $result = "\$cfg['Servers'][\$i]['table_info']  ... </th>"
            . '<td class="right">'
            . '<span class="caution"><strong>not OK</strong></span>';
        $this->assertStringContainsString(
            $result,
            $retval
        );
        // Display Features:
        $result = 'Display Features: <span class="caution">Disabled</span>';
        $this->assertStringContainsString(
            $result,
            $retval
        );

        $relationsPara['db'] = false;
        $retval = $this->relation->getRelationsParamDiagnostic($relationsPara);

        $result = __('General relation features');
        $this->assertStringContainsString(
            $result,
            $retval
        );
        $result = 'Configuration of pmadb… ';
        $this->assertStringContainsString(
            $result,
            $retval
        );
        $result = '<strong>not OK</strong>';
        $this->assertStringContainsString(
            $result,
            $retval
        );
    }

    /**
     * Test for getDisplayField
     */
    public function testPMAGetDisplayField(): void
    {
        $db = 'information_schema';
        $table = 'CHARACTER_SETS';
        $this->assertEquals(
            'DESCRIPTION',
            $this->relation->getDisplayField($db, $table)
        );

        $db = 'information_schema';
        $table = 'TABLES';
        $this->assertEquals(
            'TABLE_COMMENT',
            $this->relation->getDisplayField($db, $table)
        );

        $db = 'information_schema';
        $table = 'PMA';
        $this->assertFalse(
            $this->relation->getDisplayField($db, $table)
        );
    }

    /**
     * Test for getComments
     */
    public function testPMAGetComments(): void
    {
        $GLOBALS['cfg']['ServerDefault'] = 0;
        $_SESSION['relation'] = [];

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $getColumnsResult = [
            [
                'Field' => 'field1',
                'Type' => 'int(11)',
                'Comment' => 'Comment1',
            ],
            [
                'Field' => 'field2',
                'Type' => 'text',
                'Comment' => 'Comment1',
            ],
        ];
        $dbi->expects($this->any())->method('getColumns')
            ->will($this->returnValue($getColumnsResult));

        $GLOBALS['dbi'] = $dbi;
        $this->relation->dbi = $GLOBALS['dbi'];

        $db = 'information_schema';
        $this->assertEquals(
            [''],
            $this->relation->getComments($db)
        );

        $db = 'information_schema';
        $table = 'TABLES';
        $this->assertEquals(
            [
                'field1' => 'Comment1',
                'field2' => 'Comment1',
            ],
            $this->relation->getComments($db, $table)
        );
    }

    /**
     * Test for tryUpgradeTransformations
     */
    public function testPMATryUpgradeTransformations(): void
    {
        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->expects($this->any())
            ->method('tryQuery')
            ->will($this->returnValue(true));
        $dbi->expects($this->any())
            ->method('numRows')
            ->will($this->returnValue(0));
        $dbi->expects($this->any())
            ->method('getError')
            ->will($this->onConsecutiveCalls(true, false));
        $GLOBALS['dbi'] = $dbi;
        $this->relation->dbi = $GLOBALS['dbi'];

        $GLOBALS['cfg']['Server']['pmadb'] = 'pmadb';
        $GLOBALS['cfg']['Server']['column_info'] = 'column_info';

        // Case 1
        $actual = $this->relation->tryUpgradeTransformations();
        $this->assertFalse(
            $actual
        );

        // Case 2
        $actual = $this->relation->tryUpgradeTransformations();
        $this->assertTrue(
            $actual
        );
    }

    /**
     * @covers searchColumnInForeigners
     */
    public function testSearchColumnInForeignersError(): void
    {
        $this->assertFalse($this->relation->searchColumnInForeigners([], 'id'));
    }

    /**
     * Test for searchColumnInForeigners
     */
    public function testPMASearchColumnInForeigners(): void
    {
        $foreigners = [
            'value' => [
                'master_field' => 'value',
                'foreign_db' => 'GSoC14',
                'foreign_table' => 'test',
                'foreign_field' => 'value',
            ],
            'foreign_keys_data' => [
                0 => [
                    'constraint' => 'ad',
                    'index_list' => [
                        'id',
                        'value',
                    ],
                    'ref_db_name' => 'GSoC14',
                    'ref_table_name' => 'table_1',
                    'ref_index_list' => [
                        'id',
                        'value',
                    ],
                    'on_delete' => 'CASCADE',
                    'on_update' => 'CASCADE',
                ],
            ],
        ];

        $foreigner = $this->relation->searchColumnInForeigners($foreigners, 'id');
        $expected = [];
        $expected['foreign_field'] = 'id';
        $expected['foreign_db'] = 'GSoC14';
        $expected['foreign_table'] = 'table_1';
        $expected['constraint'] = 'ad';
        $expected['on_delete'] = 'CASCADE';
        $expected['on_update'] = 'CASCADE';

        $this->assertEquals(
            $expected,
            $foreigner
        );
    }

    public function testFixPmaTablesNothingWorks(): void
    {
        parent::setGlobalDbi();
        parent::loadDefaultConfig();

        $this->relation = new Relation($this->dbi);

        $this->dummyDbi->removeDefaultResults();
        $this->dummyDbi->addResult(
            'SHOW TABLES FROM `db_pma`;',
            false
        );

        $this->relation->fixPmaTables('db_pma', false);
        $this->assertAllQueriesConsumed();
    }

    public function testFixPmaTablesNormal(): void
    {
        parent::setGlobalDbi();
        parent::loadDefaultConfig();

        $GLOBALS['db'] = '';
        $GLOBALS['server'] = 1;
        $GLOBALS['cfg']['Server']['user'] = '';
        $GLOBALS['cfg']['Server']['pmadb'] = '';
        $GLOBALS['cfg']['Server']['bookmarktable'] = '';
        $GLOBALS['cfg']['Server']['relation'] = '';
        $GLOBALS['cfg']['Server']['table_info'] = '';
        $GLOBALS['cfg']['Server']['table_coords'] = '';
        $GLOBALS['cfg']['Server']['column_info'] = '';
        $GLOBALS['cfg']['Server']['pdf_pages'] = '';
        $GLOBALS['cfg']['Server']['history'] = '';
        $GLOBALS['cfg']['Server']['recent'] = '';
        $GLOBALS['cfg']['Server']['favorite'] = '';
        $GLOBALS['cfg']['Server']['table_uiprefs'] = '';
        $GLOBALS['cfg']['Server']['tracking'] = '';
        $GLOBALS['cfg']['Server']['userconfig'] = '';
        $GLOBALS['cfg']['Server']['users'] = '';
        $GLOBALS['cfg']['Server']['usergroups'] = '';
        $GLOBALS['cfg']['Server']['navigationhiding'] = '';
        $GLOBALS['cfg']['Server']['savedsearches'] = '';
        $GLOBALS['cfg']['Server']['central_columns'] = '';
        $GLOBALS['cfg']['Server']['designer_settings'] = '';
        $GLOBALS['cfg']['Server']['export_templates'] = '';

        $this->relation = new Relation($this->dbi);

        $this->dummyDbi->removeDefaultResults();
        $this->dummyDbi->addResult(
            'SHOW TABLES FROM `db_pma`;',
            [
                ['pma__userconfig'],
            ],
            ['Tables_in_db_pma']
        );

        $this->dummyDbi->addResult(
            'SHOW TABLES FROM `db_pma`',
            [
                ['pma__userconfig'],
            ],
            ['Tables_in_db_pma']
        );

        $this->dummyDbi->addResult(
            'SELECT NULL FROM pma__userconfig LIMIT 0',
            [['NULL']]
        );

        $this->relation->fixPmaTables('db_pma', false);
        //TODO: assert state
        $this->assertAllQueriesConsumed();
    }

    public function testFixPmaTablesNormalFixTables(): void
    {
        parent::setGlobalDbi();
        parent::loadDefaultConfig();

        $GLOBALS['db'] = '';
        $GLOBALS['server'] = 1;
        $GLOBALS['cfg']['Server']['user'] = '';
        $GLOBALS['cfg']['Server']['pmadb'] = '';
        $GLOBALS['cfg']['Server']['bookmarktable'] = '';
        $GLOBALS['cfg']['Server']['relation'] = '';
        $GLOBALS['cfg']['Server']['table_info'] = '';
        $GLOBALS['cfg']['Server']['table_coords'] = '';
        $GLOBALS['cfg']['Server']['column_info'] = '';
        $GLOBALS['cfg']['Server']['pdf_pages'] = '';
        $GLOBALS['cfg']['Server']['history'] = '';
        $GLOBALS['cfg']['Server']['recent'] = '';
        $GLOBALS['cfg']['Server']['favorite'] = '';
        $GLOBALS['cfg']['Server']['table_uiprefs'] = '';
        $GLOBALS['cfg']['Server']['tracking'] = '';
        $GLOBALS['cfg']['Server']['userconfig'] = '';
        $GLOBALS['cfg']['Server']['users'] = '';
        $GLOBALS['cfg']['Server']['usergroups'] = '';
        $GLOBALS['cfg']['Server']['navigationhiding'] = '';
        $GLOBALS['cfg']['Server']['savedsearches'] = '';
        $GLOBALS['cfg']['Server']['central_columns'] = '';
        $GLOBALS['cfg']['Server']['designer_settings'] = '';
        $GLOBALS['cfg']['Server']['export_templates'] = '';

        $this->relation = new Relation($this->dbi);

        $this->dummyDbi->removeDefaultResults();
        $this->dummyDbi->addResult(
            'SHOW TABLES FROM `db_pma`;',
            [
                ['pma__userconfig'],
            ],
            ['Tables_in_db_pma']
        );

        $this->dummyDbi->addResult(
            'SHOW TABLES FROM `db_pma`',
            [
                ['pma__userconfig'],
            ],
            ['Tables_in_db_pma']
        );

        $this->dummyDbi->addResult(
            'SELECT NULL FROM pma__userconfig LIMIT 0',
            [['NULL']]
        );

        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__bookmark` '
            . '-- CREATE TABLE IF NOT EXISTS `pma__bookmark` ( '
                . '`id` int(10) unsigned NOT NULL auto_increment,'
                . ' `dbase` varchar(255) NOT NULL default \'\','
                . ' `user` varchar(255) NOT NULL default \'\','
                . ' `label` varchar(255) COLLATE utf8_general_ci NOT NULL default \'\','
                . ' `query` text NOT NULL, PRIMARY KEY (`id`) )'
                . ' COMMENT=\'Bookmarks\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__relation` '
            . '-- CREATE TABLE IF NOT EXISTS `pma__relation` ( '
                . '`master_db` varchar(64) NOT NULL default \'\', `master_table` varchar(64) NOT NULL default \'\','
                . ' `master_field` varchar(64) NOT NULL default \'\', `foreign_db` varchar(64) NOT NULL default \'\','
                . ' `foreign_table` varchar(64) NOT NULL default \'\','
                . ' `foreign_field` varchar(64) NOT NULL default \'\','
                . ' PRIMARY KEY (`master_db`,`master_table`,`master_field`),'
                . ' KEY `foreign_field` (`foreign_db`,`foreign_table`) ) COMMENT=\'Relation table\''
                . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__table_info`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__table_info` ( '
                . '`db_name` varchar(64) NOT NULL default \'\', `table_name` varchar(64) NOT NULL default \'\','
                . ' `display_field` varchar(64) NOT NULL default \'\', PRIMARY KEY (`db_name`,`table_name`) )'
                . ' COMMENT=\'Table information for phpMyAdmin\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );

        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__table_coords`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__table_coords` ( '
                . '`db_name` varchar(64) NOT NULL default \'\', `table_name` varchar(64) NOT NULL default \'\','
                . ' `pdf_page_number` int(11) NOT NULL default \'0\', `x` float unsigned NOT NULL default \'0\','
                . ' `y` float unsigned NOT NULL default \'0\','
                . ' PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`) )'
                . ' COMMENT=\'Table coordinates for phpMyAdmin PDF output\''
                . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__pdf_pages`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__pdf_pages` ( '
                . '`db_name` varchar(64) NOT NULL default \'\', `page_nr` int(10) unsigned NOT NULL auto_increment,'
                . ' `page_descr` varchar(50) COLLATE utf8_general_ci NOT NULL default \'\', PRIMARY KEY (`page_nr`),'
                . ' KEY `db_name` (`db_name`) ) COMMENT=\'PDF relation pages for phpMyAdmin\''
                . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__column_info`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__column_info` ( '
                . '`id` int(5) unsigned NOT NULL auto_increment, `db_name` varchar(64) NOT NULL default \'\','
                . ' `table_name` varchar(64) NOT NULL default \'\', `column_name` varchar(64) NOT NULL default \'\','
                . ' `comment` varchar(255) COLLATE utf8_general_ci NOT NULL default \'\','
                . ' `mimetype` varchar(255) COLLATE utf8_general_ci NOT NULL default \'\','
                . ' `transformation` varchar(255) NOT NULL default \'\','
                . ' `transformation_options` varchar(255) NOT NULL default \'\','
                . ' `input_transformation` varchar(255) NOT NULL default \'\','
                . ' `input_transformation_options` varchar(255) NOT NULL default \'\','
                . ' PRIMARY KEY (`id`), UNIQUE KEY `db_name` (`db_name`,`table_name`,`column_name`) )'
                . ' COMMENT=\'Column information for phpMyAdmin\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__history` '
            . '-- CREATE TABLE IF NOT EXISTS `pma__history` ( '
                . '`id` bigint(20) unsigned NOT NULL auto_increment, `username` varchar(64) NOT NULL default \'\','
                . ' `db` varchar(64) NOT NULL default \'\', `table` varchar(64) NOT NULL default \'\','
                . ' `timevalue` timestamp NOT NULL default CURRENT_TIMESTAMP, `sqlquery` text NOT NULL,'
                . ' PRIMARY KEY (`id`), KEY `username` (`username`,`db`,`table`,`timevalue`) )'
                . ' COMMENT=\'SQL history for phpMyAdmin\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__recent` '
            . '-- CREATE TABLE IF NOT EXISTS `pma__recent` ( '
                . '`username` varchar(64) NOT NULL, `tables` text NOT NULL, PRIMARY KEY (`username`) )'
                . ' COMMENT=\'Recently accessed tables\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__favorite` '
            . '-- CREATE TABLE IF NOT EXISTS `pma__favorite` ( '
                . '`username` varchar(64) NOT NULL, `tables` text NOT NULL, PRIMARY KEY (`username`) )'
                . ' COMMENT=\'Favorite tables\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__table_uiprefs`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__table_uiprefs` ( '
                . '`username` varchar(64) NOT NULL, `db_name` varchar(64) NOT NULL,'
                . ' `table_name` varchar(64) NOT NULL, `prefs` text NOT NULL,'
                . ' `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
                . ' PRIMARY KEY (`username`,`db_name`,`table_name`) ) COMMENT=\'Tables\'\' UI preferences\''
                . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__tracking` '
            . '-- CREATE TABLE IF NOT EXISTS `pma__tracking` ( '
                . '`db_name` varchar(64) NOT NULL, `table_name` varchar(64) NOT NULL,'
                . ' `version` int(10) unsigned NOT NULL, `date_created` datetime NOT NULL,'
                . ' `date_updated` datetime NOT NULL, `schema_snapshot` text NOT NULL,'
                . ' `schema_sql` text, `data_sql` longtext, `tracking`'
                . ' set(\'UPDATE\',\'REPLACE\',\'INSERT\',\'DELETE\','
                . '\'TRUNCATE\',\'CREATE DATABASE\',\'ALTER DATABASE\','
                . '\'DROP DATABASE\',\'CREATE TABLE\',\'ALTER TABLE\','
                . '\'RENAME TABLE\',\'DROP TABLE\',\'CREATE INDEX\','
                . '\'DROP INDEX\',\'CREATE VIEW\',\'ALTER VIEW\',\'DROP VIEW\')'
                . ' default NULL, `tracking_active` int(1) unsigned NOT NULL'
                . ' default \'1\', PRIMARY KEY (`db_name`,`table_name`,`version`) )'
                . ' COMMENT=\'Database changes tracking for phpMyAdmin\''
                . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__users` '
            . '-- CREATE TABLE IF NOT EXISTS `pma__users` ( '
                . '`username` varchar(64) NOT NULL, `usergroup` varchar(64) NOT NULL,'
                . ' PRIMARY KEY (`username`,`usergroup`) )'
                . ' COMMENT=\'Users and their assignments to user groups\''
                . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__usergroups`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__usergroups` ( '
                . '`usergroup` varchar(64) NOT NULL, `tab` varchar(64) NOT NULL,'
                . ' `allowed` enum(\'Y\',\'N\') NOT NULL DEFAULT \'N\','
                . ' PRIMARY KEY (`usergroup`,`tab`,`allowed`) )'
                . ' COMMENT=\'User groups with configured menu items\''
                . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__navigationhiding`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__navigationhiding` ( '
                . '`username` varchar(64) NOT NULL, `item_name` varchar(64)'
                . ' NOT NULL, `item_type` varchar(64) NOT NULL, `db_name` varchar(64) NOT NULL,'
                . ' `table_name` varchar(64) NOT NULL,'
                . ' PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`) )'
                . ' COMMENT=\'Hidden items of navigation tree\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__savedsearches`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__savedsearches` ( '
                . '`id` int(5) unsigned NOT NULL auto_increment, `username` varchar(64) NOT NULL default \'\','
                . ' `db_name` varchar(64) NOT NULL default \'\', `search_name` varchar(64) NOT NULL default \'\','
                . ' `search_data` text NOT NULL, PRIMARY KEY (`id`),'
                . ' UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`) )'
                . ' COMMENT=\'Saved searches\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__central_columns`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__central_columns` ( '
                . '`db_name` varchar(64) NOT NULL, `col_name` varchar(64) NOT NULL, `col_type` varchar(64) NOT NULL,'
                . ' `col_length` text, `col_collation` varchar(64) NOT NULL, `col_isNull` boolean NOT NULL,'
                . ' `col_extra` varchar(255) default \'\', `col_default` text,'
                . ' PRIMARY KEY (`db_name`,`col_name`) )'
                . ' COMMENT=\'Central list of columns\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__designer_settings`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__designer_settings` ( '
                . '`username` varchar(64) NOT NULL, `settings_data` text NOT NULL,'
                . ' PRIMARY KEY (`username`) )'
                . ' COMMENT=\'Settings related to Designer\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__export_templates`'
            . ' -- CREATE TABLE IF NOT EXISTS `pma__export_templates` ( '
                . '`id` int(5) unsigned NOT NULL AUTO_INCREMENT, `username` varchar(64) NOT NULL,'
                . ' `export_type` varchar(10) NOT NULL, `template_name` varchar(64) NOT NULL,'
                . ' `template_data` text NOT NULL, PRIMARY KEY (`id`),'
                . ' UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`) )'
                . ' COMMENT=\'Saved export templates\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            []
        );

        $this->assertSame('', $GLOBALS['cfg']['Server']['pmadb']);
        $this->relation->fixPmaTables('db_pma', true);
        $this->assertArrayNotHasKey('message', $GLOBALS);
        $this->assertSame('db_pma', $GLOBALS['cfg']['Server']['pmadb']);
        $this->assertAllQueriesConsumed();
    }

    public function testFixPmaTablesNormalFixTablesFails(): void
    {
        parent::setGlobalDbi();
        parent::loadDefaultConfig();

        $GLOBALS['db'] = '';
        $GLOBALS['server'] = 1;
        $GLOBALS['cfg']['Server']['user'] = '';
        $GLOBALS['cfg']['Server']['pmadb'] = '';
        $GLOBALS['cfg']['Server']['bookmarktable'] = '';
        $GLOBALS['cfg']['Server']['relation'] = '';
        $GLOBALS['cfg']['Server']['table_info'] = '';
        $GLOBALS['cfg']['Server']['table_coords'] = '';
        $GLOBALS['cfg']['Server']['column_info'] = '';
        $GLOBALS['cfg']['Server']['pdf_pages'] = '';
        $GLOBALS['cfg']['Server']['history'] = '';
        $GLOBALS['cfg']['Server']['recent'] = '';
        $GLOBALS['cfg']['Server']['favorite'] = '';
        $GLOBALS['cfg']['Server']['table_uiprefs'] = '';
        $GLOBALS['cfg']['Server']['tracking'] = '';
        $GLOBALS['cfg']['Server']['userconfig'] = '';
        $GLOBALS['cfg']['Server']['users'] = '';
        $GLOBALS['cfg']['Server']['usergroups'] = '';
        $GLOBALS['cfg']['Server']['navigationhiding'] = '';
        $GLOBALS['cfg']['Server']['savedsearches'] = '';
        $GLOBALS['cfg']['Server']['central_columns'] = '';
        $GLOBALS['cfg']['Server']['designer_settings'] = '';
        $GLOBALS['cfg']['Server']['export_templates'] = '';

        $this->relation = new Relation($this->dbi);

        $this->dummyDbi->removeDefaultResults();
        $this->dummyDbi->addResult(
            'SHOW TABLES FROM `db_pma`;',
            [
                ['pma__userconfig'],
            ],
            ['Tables_in_db_pma']
        );

        // Fail the query
        $this->dummyDbi->addErrorCode('MYSQL_ERROR');
        $this->dummyDbi->addResult(
            '-- -------------------------------------------------------- -- --'
            . ' Table structure for table `pma__bookmark` '
            . '-- CREATE TABLE IF NOT EXISTS `pma__bookmark` ( '
                . '`id` int(10) unsigned NOT NULL auto_increment,'
                . ' `dbase` varchar(255) NOT NULL default \'\','
                . ' `user` varchar(255) NOT NULL default \'\','
                . ' `label` varchar(255) COLLATE utf8_general_ci NOT NULL default \'\','
                . ' `query` text NOT NULL, PRIMARY KEY (`id`) )'
                . ' COMMENT=\'Bookmarks\' DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;',
            false
        );

        $this->assertSame('', $GLOBALS['cfg']['Server']['pmadb']);
        $this->relation->fixPmaTables('db_pma', true);
        $this->assertArrayHasKey('message', $GLOBALS);
        $this->assertSame('MYSQL_ERROR', $GLOBALS['message']);
        $this->assertSame('', $GLOBALS['cfg']['Server']['pmadb']);
        $this->assertAllQueriesConsumed();
    }
}
