<?php
return array (
  'debug' => false,
  'installed' => false,
  'timezone' => 'Asia/Shanghai',
  'default_route' => true,
  'domain' => 
  array (
    0 => '0.0.0.0',
  ),
  'version' => '1.0.2',
  'framework_start' => 
  array (
    0 => 'nova\\plugin\\login\\LoginManager',
    1 => 'nova\\plugin\\tpl\\Handler',
    2 => 'nova\\plugin\\task\\Task',
    3 => 'nova\\plugin\\corn\\Schedule',
    4 => 'nova\\plugin\\corn\\CornManager',
    5 => 'nova\\plugin\\ai\\AiPluginManager',
    6 => 'nova\\plugin\\webdav\\WebdavManager',
  ),
  'db' => 
  array (
    'host' => '127.0.0.1',
    'type' => 'mysql',
    'port' => 3306,
    'username' => '',
    'password' => '',
    'db' => 'book',
    'charset' => 'utf8mb4',
  ),
  'session' => 
  array (
    'time' => 0,
    'session_name' => 'NovaSession',
  ),
  'login' => 
  array (
    'allowedLoginCount' => 1,
    'loginCallback' => '/',
    'systemName' => '我的书库',
    'ssoEnable' => false,
  ),
  'webdav' => 
  array (
    'deviceId' => '',
    'url' => '',
    'username' => '',
    'password' => '',
  ),
  'calibre' => '',
  'ai' => 
  array (
    'currentProvider' => 'ChatGPT',
    'providers' => 
    array (
      'chatgpt' => 
      array (
        'api_key' => '',
        'api_url' => '',
        'api_model' => '',
        'proxy' => '',
      ),
      'openrouter' => 
      array (
        'api_key' => '',
        'api_url' => '',
        'api_model' => '',
        'proxy' => '',
      ),
    ),
  ),
);
