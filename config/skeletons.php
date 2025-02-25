<?php
$stubsPath = 'vendor/laci/skeletons/resources/stubs/';
return [
    'path' => env('SKELETONS_PATH', base_path($stubsPath)),
    'controller' => env('SKELETONS_CONTROLLER_STUB', base_path($stubsPath . 'controller.stub')),
    'model' => env('SKELETONS_MODEL_STUB', base_path($stubsPath . 'model.stub')),
    'request' => env('SKELETONS_REQUEST_STUB', base_path($stubsPath . 'request.stub')),
    'migration' => env('SKELETONS_MIGRATION_STUB', base_path($stubsPath . 'migration.stub')),
    'seeder' => env('SKELETONS_SEEDER_STUB', base_path($stubsPath . 'seeder.stub')),
    'views.index' =>  env('SKELETONS_VIEWS_INDEX_STUB', base_path($stubsPath . 'views/index.stub')),
    'views.create' =>  env('SKELETONS_VIEWS_CREATE_STUB', base_path($stubsPath . 'views/create.stub')),
    'views.edit' =>  env('SKELETONS_VIEWS_EDIT_STUB', base_path($stubsPath . 'views/edit.stub')),
    'views.show' =>  env('SKELETONS_VIEWS_SHOW_STUB', base_path($stubsPath . 'views/show.stub')),
    // Add other stubs here
];
