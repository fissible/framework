<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Middleware;

use Fissible\Framework\Attr\ConfigAttribute;
use Fissible\Framework\Http\Middleware\Middleware;
use Fissible\Framework\Http\Request;
use Fissible\Framework\Http\Response;
use Fissible\Framework\Str;

class StaticResourceMiddleware extends Middleware
{
    public function __construct(
        #[ConfigAttribute('static-files.path', 'public')]
        private string $path
    ) {}

    public function __invoke(Request $request, $next)
    {
        $rootPath = $this->path;
        $filePath = $request->getUri()->getPath();

        if (!Str::startsWith($rootPath, DIRECTORY_SEPARATOR)) {
            $rootPath = ROOT_PATH . DIRECTORY_SEPARATOR . $rootPath;
        }

        $file = $rootPath . $filePath;

        if (file_exists($file) && !is_dir($file)) {
            $fileExt = pathinfo($file, PATHINFO_EXTENSION);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $fileType = finfo_file($finfo, $file);
            finfo_close($finfo);
            $fileContents = file_get_contents($file);

            // Fix for incorrect mime types
            switch ($fileExt) {
                case 'css':
                    $fileType = 'text/css';

                    break;
                case 'js':
                    $fileType = 'application/javascript';

                    break;
            }

            return Response::make($fileContents, 200, ['Content-Type' => $fileType]);
        }
        

        return $next($request);
    }
}