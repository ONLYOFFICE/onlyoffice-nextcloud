<?php

declare(strict_types=1);

namespace OCA\Onlyoffice\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version070000Date20211219111111 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('onlyoffice_filekey')) {
			$table = $schema->createTable('onlyoffice_filekey');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => false,
				'default' => '-1',
			]);
			$table->addColumn('key', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('lock', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('fs', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['file_id'], 'file_id_index');
		}

		if (!$schema->hasTable('onlyoffice_instance')) {
			$table = $schema->createTable('onlyoffice_instance');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('remote', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('expire', 'bigint', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('status', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['remote'], 'remote_index');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}