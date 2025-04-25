<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\DB;

trait ModelDataTrait
{
    protected string $model;
    protected object $modelInstance;
    protected string $tableName;
    protected string $primaryKey;
    protected array $fillableFields;
    protected array $columns;

    /**
     * Initializes the model instance and its related data (fillable fields, table name, etc.).
     *
     * @param string $model
     * @throws \Exception
     */
    protected function initializeModelData(string $model): void
    {
        $this->model = $model;

        $modelClass = "App\\Models\\{$this->model}";
        $modelFilePath = app_path("Models/{$this->model}.php");
        if (!file_exists($modelFilePath) || !class_exists($modelClass)) {
            throw new \Exception("Model does not exist or is not properly autoloaded: $modelClass");
        }

        $this->modelInstance = new $modelClass();
        $this->tableName = $this->modelInstance->getTable();
        $this->primaryKey = $this->modelInstance->getKeyName();

        // Fetch columns from the database
        $this->columns = DB::select("SHOW COLUMNS FROM `{$this->tableName}`");
        if (empty($this->columns)) {
            throw new \Exception("No columns found for table: {$this->tableName}. Please run the migration first.");
        }

        // Set fillable fields or fallback to database columns
        $this->fillableFields = !empty($this->modelInstance->getFillable())
            ? $this->modelInstance->getFillable()
            : array_map(fn($column) => $column->Field, $this->columns);
    }
}
