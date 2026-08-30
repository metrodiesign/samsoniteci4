<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$hook['display_override'] = array(
    'class' => 'ParityTraceHook',
    'function' => 'display',
    'filename' => 'ParityTraceHook.php',
    'filepath' => 'hooks',
);
$hook['post_controller_constructor'] = array(
    'class' => 'ParityTraceHook',
    'function' => 'bootstrapSession',
    'filename' => 'ParityTraceHook.php',
    'filepath' => 'hooks',
);
