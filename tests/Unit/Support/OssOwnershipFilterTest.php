<?php

namespace Tests\Unit\Support;

use App\Support\OssOwnershipFilter;
use PDO;
use PHPUnit\Framework\TestCase;

class OssOwnershipFilterTest extends TestCase
{
    public function test_move_excludes_historical_op_and_stale_application_from_search_and_counts(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE mls_file_no (full_file_number TEXT, system_sub_type TEXT, is_deleted INTEGER)');
        $db->exec('CREATE TABLE oss_applications (file_no TEXT, system_source TEXT, is_deleted INTEGER)');
        $db->exec('CREATE TABLE op_rows (file_no TEXT)');
        $db->exec("INSERT INTO op_rows VALUES ('RES-1'), ('RES-2'), ('RES-3'), ('RES-4'), ('RES-5')");
        $db->exec("INSERT INTO mls_file_no VALUES ('RES-1','OSS',0), ('RES-2','MLS',0), ('RES-3','MLS',0), ('RES-4','OSS',1)");
        $db->exec("INSERT INTO oss_applications VALUES ('RES-1',NULL,0), ('RES-3','OSSOPCHANGEOFNAME',NULL), ('RES-4','OSSOPCHANGEOFNAME',1)");
        $scope = OssOwnershipFilter::noChangeSql('p.file_no');
        $this->assertSame(['RES-2','RES-4','RES-5'], $db->query("SELECT p.file_no FROM op_rows p WHERE ($scope) AND (p.file_no LIKE '%RES%' OR p.file_no = 'RES-1') ORDER BY p.file_no")->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame(3, (int) $db->query("SELECT COUNT(*) FROM op_rows p WHERE $scope")->fetchColumn());
        $this->assertSame(0, (int) $db->query("SELECT COUNT(*) FROM op_rows p WHERE ($scope) AND p.file_no = 'RES-1'")->fetchColumn());
        $db->exec("UPDATE mls_file_no SET system_sub_type = 'MLS' WHERE full_file_number = 'RES-1'");
        $this->assertSame(1, (int) $db->query("SELECT COUNT(*) FROM op_rows p WHERE ($scope) AND p.file_no = 'RES-1'")->fetchColumn());
    }
}
