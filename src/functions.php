<?php declare(strict_types=1);

use Fissible\Framework\Application;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Session;

function Request()
{
    return Application::singleton()->Request ?? new Request(new \React\Http\Message\ServerRequest('', ''));
}

function Session()
{
    $Application = Application::singleton();
    if (isset($Application->Request)) {
        return $Application->Request?->Session() ?? new Session();
    }
    return new Session();
}