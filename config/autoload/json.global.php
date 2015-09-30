<?php
if(!file_exists(__DIR__ . '/config.json')){
    return [];
}

return json_decode(file_get_contents(__DIR__ . '/config.json'), true);