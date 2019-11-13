<?php namespace Tatter\Schemas;

use CodeIgniter\Config\BaseConfig;
use Tatter\Schemas\Exceptions\SchemasException;
use Tatter\Schemas\Structures\Schema;

class Schemas
{
	/**
	 * The current config.
	 *
	 * @var Tatter\Schemas\Config\Schemas
	 */
	protected $config;

	/**
	 * The current schema.
	 *
	 * @var Tatter\Schemas\Structures\Schema
	 */
	protected $schema;
	
	/**
	 * Array of error messages assigned on failure.
	 *
	 * @var array
	 */
	protected $errors = [];
	
	// Initiate library
	public function __construct(BaseConfig $config, Schema $schema = null)
	{
		$this->config = $config;
		
		// Store initial schema
		if (! is_null($schema))
		{
			$this->schema = $schema;
		}
	}

	/**
	 * Return and clear any error messages
	 *
	 * @return array  String error messages
	 */
	public function getErrors(): array
	{
		$tmpErrors    = $this->errors;
		$this->errors = [];
		return $this->errors;
	}

	/**
	 * Reset the current schema and errors
	 *
	 * @return $this
	 */
	public function reset()
	{
		$this->schema = null;
		$this->errors = [];
		
		return $this;
	}

	/**
	 * Set the current schema; used mostly for testing
	 *
	 * @return $this
	 */
	public function setSchema(Schema $schema)
	{
		$this->schema = $schema;
		
		return $this;
	}

	/**
	 * Return the current schema; if automation is enabled then read or draft a missing schema
	 *
	 * @return Schema|null  The current schema object
	 */
	public function get(): ?Schema
	{
		if (! is_null($this->schema))
		{
			return $this->schema;
		}
		
		// No schema loaded - try the default reader
		if ($this->config->automate['read'])
		{
			$this->read();
			
			if (! is_null($this->schema))
			{
				return $this->schema;
			}
		}
		
		// Still no schema - try a default draft
		if ($this->config->automate['draft'])
		{
			$this->draft();
			
			if (! is_null($this->schema))
			{
				// If the draft succeeded check if we should archive it
				if ($this->config->automate['archive'])
				{
					$this->archive();
				}

				return $this->schema;
			}
		}
		
		// Absolute failure
		if (! $this->config->silent)
		{
			throw SchemasException::forNoSchema();
		}

		$this->errors[] = lang('Schemas.noSchema');
		return null;
	}

	/**
	 * Draft a new schema from the given or default handler(s)
	 *
	 * @param array|string|null  $handlers Handler class string(s) or instance(s)
	 *
	 * @return $this
	 */
	public function draft($handlers = null)
	{
		if (empty($handlers))
		{
			$handlers = $this->config->draftHandlers;
		}
		
		// Wrap singletons
		if (! is_array($handlers))
		{
			$handlers = [$handlers];
		}
		
		// Draft and merge the schema from each handler in order
		foreach ($handlers as $handler)
		{
			if (is_string($handler))
			{
				$handler = new $handler($this->config);
			}

			if (is_null($this->schema))
			{
				$this->schema = $handler->draft();
			}
			else
			{
				$this->schema->merge($handler->draft());
			}

			$this->errors = array_merge($this->errors, $handler->getErrors());
		}

		return $this;
	}
	
	/**
	 * Archive a copy of the current schema using the handler(s)
	 *
	 * @param array|string|null  $handlers
	 *
	 * @return bool Success or failure
	 */
	public function archive($handlers = null)
	{
		if (empty($handlers))
		{
			$handlers = $this->config->archiveHandlers;
		}
		
		// Wrap singletons
		if (! is_array($handlers))
		{
			$handlers = [$handlers];
		}
		
		// Archive a copy to each handler's destination
		$result = true;
		foreach ($handlers as $handler)
		{
			if (is_string($handler))
			{
				$handler = new $handler($this->config);
			}

			$result = $result && $handler->archive($this->schema);
			
			$this->errors = array_merge($this->errors, $handler->getErrors());
		}

		return $result;
	}
	
	/**
	 * Read in a schema from the given or default handler
	 *
	 * @param array|string|null  $handlers
	 *
	 * @return $this
	 */
	public function read($handler = null)
	{
		if (empty($handler))
		{
			$handler = $this->config->readHandler;
		}

		// Create the reader instance
		if (is_string($handler))
		{
			$handler = new $handler($this->config);
		}

		$this->errors = array_merge($this->errors, $handler->getErrors());

		// Replace the current schema with a new one using the injected readHandler
		$this->schema = new Schema($handler);

		return $this;
	}
}
