<?php declare(strict_types=1);

namespace Tests\Unit\Routing;

use Fissible\Framework\Http\Controllers\Controller;
use Fissible\Framework\Http\Request;
use Illuminate\Support\Facades\Response;

class TestController extends Controller
{
    public function __invoke(Request $request)
    {
        return Response::make('Request input: '.print_r($request->input(), true));
    }
}