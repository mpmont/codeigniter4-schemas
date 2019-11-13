<?php

use Tatter\Schemas\Drafter\Handlers\DatabaseHandler;
use Tatter\Schemas\Drafter\Handlers\DirectoryHandler;
use Tatter\Schemas\Drafter\Handlers\ModelHandler;

class LiveTest extends CIModuleTests\Support\DatabaseTestCase
{
	// Probably a quite common scenario
	public function testDatabaseToCache()
	{
		$cache           = \Config\Services::cache();
		$databaseHandler = new DatabaseHandler($this->config, 'tests');
		$cacheHandler    = new \Tatter\Schemas\Archiver\Handlers\CacheHandler($this->config, $cache);
				
		$this->schemas->draft($databaseHandler)->archive($cacheHandler);
		$this->assertEmpty($this->schemas->getErrors());
		
		$schemaFromService = $this->schemas->get();
		$schemaFromCache   = $cache->get('schema:testing');
		$this->assertEquals($schemaFromCache, $schemaFromService);
		
		$this->assertObjectHasAttribute('factories', $schemaFromCache->tables);
	}
	
	public function testDatabaseMergeFile()
	{
		$databaseHandler = new DatabaseHandler($this->config, 'tests');
		$fileHandler     = new DirectoryHandler($this->config);
			
		$schema = $this->schemas->draft([$databaseHandler, $fileHandler])->get();
		
		$this->assertObjectHasAttribute('products', $schema->tables);
		$this->assertCount(3, $schema->tables->workers->relations);
	}
	
	public function testMergeAllDrafters()
	{
		$databaseHandler = new DatabaseHandler($this->config, 'tests');
		$modelHandler    = new ModelHandler($this->config);
		$fileHandler     = new DirectoryHandler($this->config);
			
		$schema = $this->schemas->draft([$databaseHandler, $modelHandler, $fileHandler])->get();
		
		$this->assertObjectHasAttribute('products', $schema->tables);
		$this->assertEquals('CIModuleTests\Support\Models\FactoryModel', $schema->tables->factories->model);
		$this->assertCount(3, $schema->tables->workers->relations);
	}
	
	public function testGetReturnsSchemaWithReader()
	{
		// Draft & archive a copy of the schema so we can test reading it
		$result = $this->schemas->draft()->archive();
		$this->assertTrue($result);
		
		$this->schemas->reset();
		
		$schema = $this->schemas->read()->get();
		
		$this->assertInstanceOf('\Tatter\Schemas\Reader\BaseReader', $schema->tables);
	}
	
	public function testAutoRead()
	{
		$this->config->automate['read'] = true;
		
		// Draft & archive a copy of the schema so we can test reading it
		$result = $this->schemas->draft()->archive();
		$this->assertTrue($result);
		
		$this->schemas->reset();

		$schema = $this->schemas->get();

		$this->assertEquals('CIModuleTests\Support\Models\FactoryModel', $schema->tables->factories->model);
		$this->assertCount(3, $schema->tables->workers->relations);
	}
}
