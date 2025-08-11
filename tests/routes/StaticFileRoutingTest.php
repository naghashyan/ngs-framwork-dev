<?php
/**
 * StaticFileRoutingTest
 *
 * Echo-based test to verify that NgsRoutesResolver produces NgsFileRoute for
 * static assets, and that css/less/sass switching is applied.
 */

namespace ngs\tests\routes {

require_once __DIR__ . '/../../src/routes/NgsRoutesResolver.php';
require_once __DIR__ . '/../../src/routes/NgsRoute.php';
require_once __DIR__ . '/../../src/routes/NgsFileRoute.php';
require_once __DIR__ . '/../../src/NGSModule.php';
require_once __DIR__ . '/../../src/util/NgsEnvironmentContext.php';
require_once __DIR__ . '/../../src/exceptions/NotFoundException.php';
require_once __DIR__ . '/../../src/exceptions/DebugException.php';

use ngs\routes\NgsRoutesResolver;

// Minimal NGS() stub suitable for route resolving
}

namespace {
  if (!function_exists('NGS')) {
    function NGS() {
      static $instance = null;
      if ($instance === null) {
        $instance = new class {
          private array $data = [];
          public function set($k,$v){$this->data[$k]=$v;return $this;}
          public function get($k){return $this->data[$k] ?? null;}
          public function getDefinedValue($k){return $this->get($k);}          
          public function getName(){ return 'ngs'; }
          public function getModuleDirByNS($ns){ return __DIR__; }
          public function createDefinedInstance($key,$class){
            switch ($key){
              case 'MODULES_ROUTES_ENGINE':
                // Provide a minimal module resolver-like stub if called
                return new class {
                  public function resolveModule($uri){
                    return new class extends \ngs\NgsModule {
                      public function __construct(){ parent::__construct(null, self::MODULE_TYPE_PATH); }
                      public function getName(): string { return 'moduleA'; }
                      public function getDir(): string { return getcwd(); }
                    };
                  }
                  public function getDefaultNS(){ return 'ngs'; }
                };
              default:
                return new $class();
            }
          }
        };
        // Sensible defaults
        $instance
          ->set('DYN_URL_TOKEN', 'dyn')
          ->set('PUBLIC_DIR', 'public')
          ->set('JS_DIR', 'js')
          ->set('CSS_DIR', 'css')
          ->set('LESS_DIR', 'less')
          ->set('SASS_DIR', 'sass');
      }
      return $instance;
    }
  }
}

namespace ngs\tests\routes {
  class DummyModule extends \ngs\NgsModule {
    public function __construct(string $dir){ parent::__construct($dir, self::MODULE_TYPE_PATH); }
    public function getName(): string { return 'moduleA'; }
  }

  echo "\n[StaticFileRoutingTest] Starting...\n";
  $resolver = new \ngs\routes\NgsRoutesResolver();
  $moduleDir = sys_get_temp_dir() . '/ngs_route_mod_' . uniqid();
  @mkdir($moduleDir, 0777, true);
  $module = new DummyModule($moduleDir);

  // Case 1: /moduleA/js/app.js → NgsFileRoute js
  $url1 = '/moduleA/js/app.js';
  $route1 = $resolver->resolveRoute($module, $url1);
  $pass1 = ($route1 instanceof \ngs\routes\NgsFileRoute)
           && $route1->getFileType() === 'js'
           && $route1->getFileUrl() === 'js/app.js';
  echo "Static js route resolves to NgsFileRoute: " . ($pass1 ? 'PASS' : 'FAIL') . "\n";

  // Case 2: /moduleA/css/less/app.css → NgsFileRoute less
  $url2 = '/moduleA/css/less/app.css';
  $route2 = $resolver->resolveRoute($module, $url2);
  $pass2 = ($route2 instanceof \ngs\routes\NgsFileRoute)
           && $route2->getFileType() === 'less'
           && $route2->getFileUrl() === 'css/less/app.css';
  echo "Static css/less route switches to less: " . ($pass2 ? 'PASS' : 'FAIL') . "\n";

  echo "[StaticFileRoutingTest] Completed.\n";
}
