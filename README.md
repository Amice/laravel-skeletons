# laravel-skeletons
---
### Laravel Skeletons Generator Command

The `app:make-skeletons` command is a comprehensive migration-driven code-generation tool for Laravel applications. It automates the creation of your app’s core scaffolding with the following key features:

- **Migration Parsing and Related Entities Discovery:**  
  The command reads a specified migration file to extract the table name, column definitions, and relationships. It automatically detects foreign key relationships and recursively examines related migrations. This means it generates all necessary files (models, controllers, views, seeders, etc.) for your main table as well as for any related tables, ensuring complete scaffolding for your database schema.

- **File Generation:**  
  Based on the parsed migration data, the command automatically generates:

   - **Models:** Complete with fillable attributes, relationships, and configuration (such as timestamp settings).
   - **Controllers:** Ready-to-use controllers with stubs tailored for your chosen CSS framework (Bootstrap or Tailwind).
   - **Requests:** Fields marked as non-nullable in your migration are automatically assigned the required validation rule when creating or updating records. Additionally, corresponding language files containing validation messages prefixed with `validate_` are generated to facilitate multi-language support. 
   - **Views:** CRUD views that integrate with your layout and support multilingual setups. If auth middleware is detected Views for data-modifying operations are automatically secured, only authenticated users will have access.
   - **Seeders:**  
     Bulk import logic that dynamically adjusts for performance with large datasets and includes progress feedback.  
     **Note:** Data source CSV files should be placed in the `seeders/data` folder with the same name as the migration’s table name (e.g., if your migration creates a `users` table, the corresponding CSV file should be named `users.csv`).
   - **Routes:**  
     A separate route file is generated for each table, with the file name derived from the table name. These route files contain common CRUD route definitions, for example:

     ```php
     Route::post('/examples', [ExampleController::class, 'store'])->name('examples.store');
     Route::get('/examples/create', [ExampleController::class, 'create'])->name('examples.create');
     Route::patch('/examples/{example}', [ExampleController::class, 'update'])->name('examples.update');
     Route::get('/examples/{example}/edit', [ExampleController::class, 'edit'])->name('examples.edit');
     Route::delete('/examples/{example}', [ExampleController::class, 'destroy'])->name('examples.destroy');
     Route::get('/examples', [ExampleController::class, 'index'])->name('examples.index');
     Route::get('/examples/{example}', [ExampleController::class, 'show'])->name('examples.show');
     Route::post('/examples/search', [ExampleController::class, 'search'])->name('examples.search');
     ```

     Additionally, if authentication middleware is available in your application, the generated routes for data-modifying operations (store, update, destroy, etc.) are automatically wrapped with it to ensure secure access. 
	 
	 ```php
	 // Routes not requiring authentication
	 Route::get('/examples', [ExampleController::class, 'index'])->name('examples.index');
	 Route::post('/examples/search', [ExampleController::class, 'search'])->name('examples.search');

	 // Routes requiring authentication
	 Route::middleware('auth')->group(function () {
		Route::post('/{{ table_name }}', [{{ controller }}::class, 'store'])->name('{{ table_name }}.store');
		Route::post('/examples', [ExampleController::class, 'store'])->name('examples.store');
		Route::get('/examples/create', [ExampleController::class, 'create'])->name('examples.create');
		Route::patch('/examples/{example}', [ExampleController::class, 'update'])->name('examples.update');
		Route::get('/examples/{example}/edit', [ExampleController::class, 'edit'])->name('examples.edit');
		Route::delete('/examples/{example}', [ExampleController::class, 'destroy'])->name('examples.destroy');
	 });
	 Route::get('/examples/{example}', [ExampleController::class, 'show'])->name('examples.show');
	```
	All generated route files are automatically required in your main web.php file, so your routes are seamlessly integrated into the application.
	
- **Language Files Generation and Copy:**  
  The command generates a separate language file for each model. The file name is derived directly from the table name. These generated language files are then copied recursively into your project’s `resources/lang` folder, preserving any subdirectory structure for different locales.  
  
- **Customization Options:**  
  Users can tailor the generation process with a variety of command-line options, such as:
   - Selecting which CSS framework to use (Bootstrap or Tailwind).
   - Controlling whether a copyright header is included in generated files.
   - Adjusting batch sizes for seeder file insertion when dealing with large datasets.

By automating these repetitive tasks, intelligently discovering table relationships, managing data, route, and translation files seamlessly, and enforcing naming conventions, the `app:make-skeletons` command can significantly speed up your development process—allowing you to focus on building application-specific features rather than boilerplate code.

**Important:** As the tool is built for the Laravel ecosystem, it expects table names to be in English plural form. Not adhering to this rule might lead to unexpected results in file naming and translation management.

### Overview

**Language Settings:**
- **Environment Variables:**  
  Update your `.env` file to specify your preferred language by setting:
  ```
  APP_LOCALE=en
  APP_FALLBACK_LOCALE=en
  ```
  These values tell the application which language files to load and use as fallbacks.

**Paginator Setup:**
- **Configuration:**  
  In your `config/app.php` file, add a key for pagination (with a default value from the environment):
  ```php
  'pagination_limit' => env('APP_PAGINATION_LIMIT', 20),
  ```
- **.env Configuration:**  
  Then, in your `.env` file, define:
  ```
  APP_PAGINATION_LIMIT=20
  ```
  This ensures that your paginated views will display the desired number of items per page.

**Usage Instructions:**
1. **Installation:**  
   Run the following Composer command to install the package as a development dependency:
   ```
   composer require --dev kovacs-laci/laravel-skeletons
   ```

2. **Generating Files:**  
   To generate the scaffolding, run the artisan command with the appropriate options. For example, if your migration file creates a table called `examples`, execute:
   ```
   php artisan app:make-skeletons --migration=create_examples_table
   ```
   *Note:* The migration timestamp can be omitted.

   **Optional Flags:**
   - `--migration=` : The migration file to be used, e.g. create_products_table.
   - `--css-style=plain` : The CSS style to apply. Available options: plain, bootstrap, tailwind.
   - `--with-auth` : Include authentication support in the generated code.
   - `--no-copyright` : If set, generated files will omit the copyright header.
   - `--cleanup` : Remove all `.bak` files from the folders and exit.
   - `--purge` : Remove all generated files for the given migration except `.bak` files.

**Final Note:**  
After following these steps, you’ll have your application’s language, pagination, and code scaffolding set up as intended. 

**Happy coding!**
