<?php
return ['routes'=>[
 ['name'=>'page#index','url'=>'/','verb'=>'GET'],
 ['name'=>'page#search','url'=>'/suchbogen','verb'=>'GET'],
 ['name'=>'shift#state','url'=>'/api/state','verb'=>'GET'],
 ['name'=>'shift#create','url'=>'/api/shifts','verb'=>'POST'],
 ['name'=>'shift#split','url'=>'/api/shifts/{id}/split','verb'=>'POST','requirements'=>['id'=>'\\d+']],
 ['name'=>'shift#accept','url'=>'/api/shifts/{id}/accept','verb'=>'POST','requirements'=>['id'=>'\\d+']],
 ['name'=>'shift#reopen','url'=>'/api/shifts/{id}/reopen','verb'=>'POST','requirements'=>['id'=>'\\d+']],
 ['name'=>'shift#contact','url'=>'/api/shifts/{id}/contacts','verb'=>'POST','requirements'=>['id'=>'\\d+']],
 ['name'=>'shift#confirm','url'=>'/api/shifts/{id}/confirm','verb'=>'POST','requirements'=>['id'=>'\\d+']],
 ['name'=>'retention#show','url'=>'/api/privacy/retention','verb'=>'GET'],
 ['name'=>'retention#save','url'=>'/api/privacy/retention','verb'=>'POST'],
 ['name'=>'permission_settings#save','url'=>'/api/admin/permissions','verb'=>'PUT'],
]];
