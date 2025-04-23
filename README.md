# laravel-skeletons
An artisan command to create the most common MVC files with content although without any css. It creates a skeleton for
 - controller, 
 - model, 
 - views, 
 - migration, 
 - request, 
 - seeder

It's able to make index, create, edit and show views using the model->fillable property. 

It generates a sample file for layout into folder: 

    resources/views/layouts/app.example.blade.php. 

Just rename it to app.blade.php and add menu items.

The generated index view contains a search field and paginator. 

## Setting up paginator

To setup the paginator add to config/app.php

    'pagination_limit' => env('APP_PAGINATION_LIMIT', 20),

and to .env file:

    APP_PAGINATION_LIMIT=20


# How to use
1. Install
   
    <code>composer require --dev kovacs-laci/laravel-skeletons</code>


2. Follow these steps:

    2.1. Run the following command, where table_name is the name of database table and model_name is the name of model representing the database table. 

        php artisan app:make-skeletons model_name table_name

    This command will create the following files:

        Generating files for: Example (example / examples)...
        
        ✔ Created: <project-folder>\app\Http\Controllers\ExampleController.php
        
        ✔ Created: <project-folder>\app\Models\Example.php
        
        ✔ Created: <project-folder>\app\Http\Requests\ExampleRequest.php
        
        ✔ Created: <project-folder>\database\migrations\2025_04_23_192113_create_examples_table.php
        
        ✔ Created: <project-folder>\database\seeders\ExampleSeeder.php
        
        ✔ Created: <project-folder>\resources\views\examples\index.blade.php
        
        ✔ Created: <project-folder>\resources\views\examples\create.blade.php
        
        ✔ Created: <project-folder>\resources\views\examples\edit.blade.php
        
        ✔ Created: <project-folder>\resources\views\examples\show.blade.php
        
        ✅ All files generated successfully!
        
        ✅ web.php has been updated successfully!
        
        ✅ Language files have been created successfully!
        
        ❗ To create the database table(s) don't forget to run command: php artisan migrate

    2.2 Add the necessary fields to the migration file.

    2.3 Add fillable fields to the model.

    2.4 Run the migrate command:
    
        php artisan migrate

    2.5 Run the following command:

        php artisan app:make-skeletons update-views

    This command will add fillable fields to the views. 

    When fillable property is not set the database fields will be added to lists and forms.

