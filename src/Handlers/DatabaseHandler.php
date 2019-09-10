<?php namespace Tatter\Schemas\Handlers;

use CodeIgniter\Config\BaseConfig;
use Tatter\Schemas\Interfaces\SchemaHandlerInterface;
use Tatter\Schemas\Structures\Schema;
use Tatter\Schemas\Structures\Relation;
use Tatter\Schemas\Structures\Table;
use Tatter\Schemas\Structures\Field;
use Tatter\Schemas\Structures\Index;
use Tatter\Schemas\Structures\ForeignKey;

class DatabaseHandler extends BaseHandler implements SchemaHandlerInterface
{
	/**
	 * The main database connection.
	 *
	 * @var ConnectionInterface
	 */
	protected $db;

	/**
	 * The pattern used to identify potention relationship fields.
	 *
	 * @var string
	 */
	protected $fieldRegex = '/^.+_id$/';
	
	// Initiate library
	public function __construct(BaseConfig $config = null, $db = null)
	{		
		parent::__construct($config);
		
		// Use injected database connection, or start a new one with the default group
		$this->db = db_connect($db);
	}
	
	// Map the database from $this->db into a new schema
	public function import(): Schema
	{
		// Start with a fresh Schema
		$schema = new Schema();
		
		// Track possible relations to check
		$tableRelations = [];
		$fieldRelations = [];
		
		// Proceed table by table
		foreach ($this->db->listTables() as $tableName)
		{
			// Start a new table
			$table = new Table($tableName);
			
			// Check for a relation table indicator
			if (strpos($tableName, '_') !== false)
			{
				$tableRelations[] = $tableName;
			}
			
			// Proceed field by field
			foreach ($this->db->getFieldData($tableName) as $fieldData)
			{
				// Start a new field
				$field = new Field($fieldData);
				
				// Check for a relation field indicator
				if (! $field->primary_key && preg_match($this->fieldRegex, $field->name))
				{
					if (! isset($fieldRelations[$tableName]))
					{
						$fieldRelations[$tableName] = [];
					}
					$fieldRelations[$tableName][] = $field->name;
				}
				
				// Add the field to the table
				$table->fields[$field->name] = $field;
			}
			
			// Proceed index by index
			foreach ($this->db->getIndexData($tableName) as $indexData)
			{
				// Start a new index
				$index = new Index($indexData);
				
				// Add the index to the table
				$table->indexes[$index->name] = $index;
			}
			
			// Proceed FK by FK
			foreach ($this->db->getForeignKeyData($tableName) as $foreignKeyData)
			{
				// Start a new foreign key
				$foreignKey = new ForeignKey($foreignKeyData);
				
				// Add the FK to the table
				$table->foreignKeys[$foreignKey->constraint_name] = $foreignKey;
				
				// Create a relation
				$relation = new Relation();
				$relation->table = $foreignKey->foreign_table_name;
				
				// Not all drivers supply the column names
				if (isset($foreignKey->column_name))
				{
					$pivot = [
						$foreignKey->foreign_table_name,
						$foreignKey->column_name,
						$foreignKey->foreign_column_name,
					];
					$relation->pivots = [$pivot];
				}
				
				// Add the relation to the table
				$table->relations[$relation->table] = $relation;
			}
			
			// Add the table to the schema
			$schema->tables[$table->name] = $table;
		}
		
		// Check tables flagged as possible pivots
		foreach ($tableRelations as $tableName)
		{
			list($tableName1, $tableName2) = explode('_', $tableName, 2);
			
			// Check for both tables (e.g. `groups_users` must have `groups` and `users`)			
			if (isset($this->tables[$tableName1]) && isset($this->tables[$tableName2]))
			{
				// A match! Look for foreign fields (may not be properly keyed)
				$fieldName1    = $this->findKeyToForeignTable($this->tables[$tableName], $tableName1);
				$foreignField1 = $this->findPrimaryKey($this->tables[$tableName1]);
				
				$fieldName2    = $this->findKeyToForeignTable($this->tables[$tableName], $tableName2);
				$foreignField2 = $this->findPrimaryKey($this->tables[$tableName2]);
				
				// If all fields were found we have a match
				if ($fieldName1 && $fieldName2 && $foreignField1 && $foreignField2)
				{
					// Note the table as a pivot & clear its relations
					$this->tables[$tableName]->pivot = true;
					$this->tables[$tableName]->relations = [];

					// Build the pivots
					$pivot1 = [
						$tableName,       // groups_users
						$foreignField1,   // id
						$fieldName1,      // group_id
					];
					$pivot2 = [
						$tableName2,      // users
						$fieldName2,      // user_id
						$foreignField2,   // id
					];
					
					// Build the relation
					$relation = new Relation();
					$relation->type   = 'manyToMany';
					$relation->table  = $tableName2;
					$relation->pivots = [$pivot1, $pivot2];
					
					// Add it to the first table
					$this->tables[$tableName1]->relations[] = $relation;

					// Build the pivots
					$pivot1 = [
						$tableName,       // groups_users
						$foreignField2,   // id
						$fieldName2,      // user_id
					];
					$pivot2 = [
						$tableName1,      // groups
						$fieldName1,      // group_id
						$foreignField1,   // id
					];
					
					// Build the relation
					$relation = new Relation();
					$relation->type   = 'manyToMany';
					$relation->table  = $tableName1;
					$relation->pivots = [$pivot1, $pivot2];
					
					// Add it to the first table
					$this->tables[$tableName2]->relations[] = $relation;
				}
			}
		}
		
		return $schema;
	}
	
	// Create all the schema structures in the database
	public function export(Schema $schema)
	{
		
	}
}
