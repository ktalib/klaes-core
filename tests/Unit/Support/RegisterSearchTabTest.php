<?php
namespace Tests\Unit\Support;

use App\Support\RegisterSearchTab;
use Illuminate\Database\Capsule\Manager;
use PHPUnit\Framework\TestCase;

class RegisterSearchTabTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        $capsule = new Manager;
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db = $capsule->getConnection();
        $this->db->statement('CREATE TABLE records (file_number TEXT, applicant_name TEXT, location TEXT, batch_mother_file_no TEXT, rofo_batch_id TEXT, tab TEXT)');
        $this->db->table('records')->insert([
            ['file_number'=>'LAND-12', 'applicant_name'=>'Ada', 'location'=>'North', 'batch_mother_file_no'=>null, 'rofo_batch_id'=>null, 'tab'=>'printed'],
            ['file_number'=>'LAND-123', 'applicant_name'=>'Ada', 'location'=>'South', 'batch_mother_file_no'=>null, 'rofo_batch_id'=>null, 'tab'=>'not_printed'],
            ['file_number'=>'OSS-9', 'applicant_name'=>'Ben', 'location'=>'West', 'batch_mother_file_no'=>null, 'rofo_batch_id'=>null, 'tab'=>'oss'],
            ['file_number'=>'CHILD-1', 'applicant_name'=>'Chi', 'location'=>'East', 'batch_mother_file_no'=>'MOTHER-1', 'rofo_batch_id'=>'BATCH-1', 'tab'=>'batches'],
        ]);
    }

    /** @dataProvider searches */
    public function test_selects_tab_containing_results($current, $search, $expected): void
    {
        $queries=[];
        foreach (['not_printed','printed','oss','batches'] as $tab) {
            $queries[$tab]=$this->db->table('records')->where('tab',$tab);
        }
        $this->assertSame($expected, RegisterSearchTab::resolve($queries,$current,$search));
        $this->assertSame(1,$queries['printed']->count(), 'Search must not mutate reusable tab queries');
    }

    public static function searches(): array
    {
        return [
            ['not_printed','LAND-12','printed'],
            ['printed','LAND-123','not_printed'],
            ['not_printed','OSS-9','oss'],
            ['oss','MOTHER-1','batches'],
            ['printed','BATCH-1','batches'],
            ['printed','CHILD-1','batches'],
            ['not_printed','Ada','not_printed'],
            ['oss','North','printed'],
            ['printed','missing','printed'],
            ['oss',"' OR 1=1 --",'oss'],
        ];
    }
}
