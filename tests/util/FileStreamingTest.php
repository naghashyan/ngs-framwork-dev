<?php
/**
 * FileStreamingTest
 *
 * Lightweight vendor-style test script (echo-based) that exercises Dispatcher::streamStaticFile
 * selection logic across file types. It stubs NGS(), FileUtils, and Builders to
 * observe which component is invoked.
 */

namespace ngs\tests\util {

// Includes
require_once __DIR__ . '/../../src/Dispatcher.php';
require_once __DIR__ . '/../../src/NGSModule.php';
require_once __DIR__ . '/../../src/routes/NgsRoute.php';
require_once __DIR__ . '/../../src/routes/NgsFileRoute.php';
require_once __DIR__ . '/../../src/util/AbstractBuilder.php';
require_once __DIR__ . '/../../src/util/FileUtils.php';
require_once __DIR__ . '/../../src/util/JsBuilderV2.php';
require_once __DIR__ . '/../../src/util/CssBuilder.php';
require_once __DIR__ . '/../../src/util/LessBuilder.php';
require_once __DIR__ . '/../../src/util/SassBuilder.php';
require_once __DIR__ . '/../../src/util/NgsEnvironmentContext.php';
require_once __DIR__ . '/../../src/routes/NgsModuleResolver.php';
require_once __DIR__ . '/../../src/event/EventManagerInterface.php';
require_once __DIR__ . '/../../src/event/EventManager.php';
require_once __DIR__ . '/../../src/event/structure/AbstractEventStructure.php';
require_once __DIR__ . '/../../src/event/structure/BeforeResultDisplayEventStructure.php';

use ngs\Dispatcher;
use ngs\routes\NgsFileRoute;

// Basic helpers for temp fixtures
function fs_mkdirp(string $dir): void {
  if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
  }
}

// Mock classes capturing calls
class SpyFileUtils extends \ngs\util\FileUtils {
  public static array $calls = [];
  public function sendFile(string $path, array $options = []): void {
    self::$calls[] = ['method' => 'sendFile', 'path' => $path, 'options' => $options];
    echo "SpyFileUtils::sendFile called with $path\n";
  }
  public function streamFile(string $path): void {
    self::$calls[] = ['method' => 'streamFile', 'path' => $path, 'options' => []];
    echo "SpyFileUtils::streamFile called with $path\n";
  }
}
class SpyJsBuilder extends \ngs\util\JsBuilderV2 {
  public static array $calls = [];
  public function streamFile(string $filePath): void {
    self::$calls[] = ['method' => 'streamFile', 'path' => $filePath, 'type' => 'js'];
    echo "SpyJsBuilder::streamFile called with $filePath\n";
  }
}
class SpyCssBuilder extends \ngs\util\CssBuilder {
  public static array $calls = [];
  public function streamFile(string $filePath): void {
    self::$calls[] = ['method' => 'streamFile', 'path' => $filePath, 'type' => 'css'];
    echo "SpyCssBuilder::streamFile called with $filePath\n";
  }
}
class SpyLessBuilder extends \ngs\util\LessBuilder {
  public static array $calls = [];
  public function streamFile(string $filePath): void {
    self::$calls[] = ['method' => 'streamFile', 'path' => $filePath, 'type' => 'less'];
    echo "SpyLessBuilder::streamFile called with $filePath\n";
  }
}
class SpySassBuilder extends \ngs\util\SassBuilder {
  public static array $calls = [];
  public function streamFile(string $filePath): void {
    self::$calls[] = ['method' => 'streamFile', 'path' => $filePath, 'type' => 'sass'];
    echo "SpySassBuilder::streamFile called with $filePath\n";
  }
}
}

// Provide an NGS() stub for tests if not already defined
namespace {
  if (!function_exists('NGS')) {
    function NGS() {
      static $instance = null;
      if ($instance === null) {
        $instance = new class {
          private array $data = [];
          public function set($k, $v) { $this->data[$k] = $v; return $this; }
          public function get($k) { return $this->data[$k] ?? null; }
          public function getDefinedValue($k) { return $this->get($k); }
          public function getName() { return 'ngs'; }
          public function createDefinedInstance($key, $class) {
            // Return spies where applicable
            switch ($key) {
              case 'FILE_UTILS': return new \ngs\tests\util\SpyFileUtils();
              case 'JS_BUILDER': return new \ngs\tests\util\SpyJsBuilder();
              case 'CSS_BUILDER': return new \ngs\tests\util\SpyCssBuilder();
              case 'LESS_BUILDER': return new \ngs\tests\util\SpyLessBuilder();
              case 'SASS_BUILDER': return new \ngs\tests\util\SpySassBuilder();
              case 'REQUEST_CONTEXT':
                return new class {
                  public function getRequestUri() { return '/'; }
                  public function isAjaxRequest() { return false; }
                  public function redirect($u) { echo "redirect to $u\n"; }
                  public function getHttpHost(bool $proto = true, bool $withSlash = true) { return 'http://localhost'; }
                  public function getHttpHostByNs($module = '') { return 'http://localhost'; }
                };
              case 'ROUTES_ENGINE':
                // Not used when passing a route manually to Dispatcher
                return new class {};
              case 'TEMPLATE_ENGINE':
                return new class {
                  public function setHttpStatusCode($c) {}
                  public function assign($k,$v) {}
                  public function assignParams($p) {}
                  public function setType($t) {}
                  public function setTemplate($t) {}
                  public function setPermalink($p) {}
                  public function display($json = false) {}
                };
              case 'SESSION_MANAGER':
                return new class { public function validateRequest($r) { return true; } };
              case 'LOAD_MAPPER':
                return new class { public function getNgsPermalink() { return '/'; } };
            }
            return new $class();
          }
          public function getModuleDirByNS($ns) {
            return __DIR__;
          }
        };
        // Defaults needed by builders/dispatcher path resolution
        $instance
          ->set('PUBLIC_DIR', 'public')
          ->set('PUBLIC_OUTPUT_DIR', 'out')
          ->set('JS_DIR', 'js')
          ->set('CSS_DIR', 'css')
          ->set('LESS_DIR', 'less')
          ->set('SASS_DIR', 'sass')
          ->set('ENVIRONMENT', 'development')
          ->set('JS_BUILD_MODE', 'development')
          ->set('LESS_BUILD_MODE', 'development')
          ->set('SASS_BUILD_MODE', 'development');
      }
      return $instance;
    }
  }
}

namespace ngs\tests\util {
  // Minimal Module stub
  class TestModule extends \ngs\NgsModule {
    private string $name; private string $dir;
    public function __construct(string $name, string $dir) { $this->name = $name; $this->dir = $dir; }
    public function getName(): string { return $this->name; }
    public function getDir(): string { return $this->dir; }
  }

  // Run tests
  echo "\n[FileStreamingTest] Starting...\n";

  $baseTmp = sys_get_temp_dir() . '/ngs_fs_test_' . uniqid();
  $moduleDir = $baseTmp . '/moduleA';
  $publicDir = $moduleDir . '/'. \NGS()->get('PUBLIC_DIR');
  fs_mkdirp($publicDir);
  // Create type subfolders
  fs_mkdirp($publicDir . '/' . \NGS()->get('JS_DIR'));
  fs_mkdirp($publicDir . '/' . \NGS()->get('CSS_DIR'));
  fs_mkdirp($publicDir . '/' . \NGS()->get('LESS_DIR'));
  fs_mkdirp($publicDir . '/' . \NGS()->get('SASS_DIR'));

  $module = new TestModule('moduleA', $moduleDir);

  // Case 1: existing file → FileUtils
  $existingPathRel = \NGS()->get('JS_DIR') . '/exist.js';
  $existingPathAbs = $publicDir . '/' . $existingPathRel;
  file_put_contents($existingPathAbs, 'console.log("ok");');

  $route1 = new \ngs\routes\NgsFileRoute();
  $route1->setMatched(true);
  $route1->setModule($module);
  $route1->setFileUrl($existingPathRel);
  $route1->setFileType('js');

  // Reset spies
  \ngs\tests\util\SpyFileUtils::$calls = [];
  (new \ngs\Dispatcher())->dispatch($route1);
  $pass1 = count(\ngs\tests\util\SpyFileUtils::$calls) === 1;
  echo "Existing file via FileUtils: " . ($pass1 ? 'PASS' : 'FAIL') . "\n";

  // Case 2: missing js → JsBuilderV2
  $missingRel = \NGS()->get('JS_DIR') . '/missing.js';
  $route2 = new \ngs\routes\NgsFileRoute();
  $route2->setMatched(true);
  $route2->setModule($module);
  $route2->setFileUrl($missingRel);
  $route2->setFileType('js');

  \ngs\tests\util\SpyJsBuilder::$calls = [];
  (new \ngs\Dispatcher())->dispatch($route2);
  $pass2 = count(\ngs\tests\util\SpyJsBuilder::$calls) === 1;
  echo "Missing js via JsBuilderV2: " . ($pass2 ? 'PASS' : 'FAIL') . "\n";

  // Case 3: missing css → CssBuilder
  $missingCssRel = \NGS()->get('CSS_DIR') . '/styles.css';
  $route3 = new \ngs\routes\NgsFileRoute();
  $route3->setMatched(true);
  $route3->setModule($module);
  $route3->setFileUrl($missingCssRel);
  $route3->setFileType('css');

  \ngs\tests\util\SpyCssBuilder::$calls = [];
  (new \ngs\Dispatcher())->dispatch($route3);
  $pass3 = count(\ngs\tests\util\SpyCssBuilder::$calls) === 1;
  echo "Missing css via CssBuilder: " . ($pass3 ? 'PASS' : 'FAIL') . "\n";

  // Case 4: missing less → LessBuilder
  $missingLessRel = \NGS()->get('LESS_DIR') . '/bundle.css';
  $route4 = new \ngs\routes\NgsFileRoute();
  $route4->setMatched(true);
  $route4->setModule($module);
  $route4->setFileUrl($missingLessRel);
  $route4->setFileType('less');

  \ngs\tests\util\SpyLessBuilder::$calls = [];
  (new \ngs\Dispatcher())->dispatch($route4);
  $pass4 = count(\ngs\tests\util\SpyLessBuilder::$calls) === 1;
  echo "Missing less via LessBuilder: " . ($pass4 ? 'PASS' : 'FAIL') . "\n";

  // Case 5: missing sass → SassBuilder
  $missingSassRel = \NGS()->get('SASS_DIR') . '/bundle.css';
  $route5 = new \ngs\routes\NgsFileRoute();
  $route5->setMatched(true);
  $route5->setModule($module);
  $route5->setFileUrl($missingSassRel);
  $route5->setFileType('sass');

  \ngs\tests\util\SpySassBuilder::$calls = [];
  (new \ngs\Dispatcher())->dispatch($route5);
  $pass5 = count(\ngs\tests\util\SpySassBuilder::$calls) === 1;
  echo "Missing sass via SassBuilder: " . ($pass5 ? 'PASS' : 'FAIL') . "\n";

  // Case 6: missing unknown type (png) → default FileUtils::streamFile
  $missingPngRel = 'img/icon.png';
  $route6 = new \ngs\routes\NgsFileRoute();
  $route6->setMatched(true);
  $route6->setModule($module);
  $route6->setFileUrl($missingPngRel);
  $route6->setFileType('png');

  \ngs\tests\util\SpyFileUtils::$calls = [];
  (new \ngs\Dispatcher())->dispatch($route6);
  $lastCall6 = end(\ngs\tests\util\SpyFileUtils::$calls);
  $pass6 = is_array($lastCall6) && ($lastCall6['method'] ?? '') === 'streamFile';
  echo "Missing unknown type via FileUtils::streamFile: " . ($pass6 ? 'PASS' : 'FAIL') . "\n";

  // Case 7: Dispatcher rewrite js/ngs → js/admin/ngs
  $rewriteRel = \NGS()->get('JS_DIR') . '/ngs/app.js';
  $route7 = new \ngs\routes\NgsFileRoute();
  $route7->setMatched(true);
  $route7->setModule($module);
  $route7->setFileUrl($rewriteRel);
  $route7->setFileType('js');

  \ngs\tests\util\SpyJsBuilder::$calls = [];
  (new \ngs\Dispatcher())->dispatch($route7);
  $rewrittenUrl = $route7->getFileUrl();
  $pass7 = $rewrittenUrl === (\NGS()->get('JS_DIR') . '/admin/ngs/app.js');
  echo "Dispatcher js/ngs rewrite to js/admin/ngs: " . ($pass7 ? 'PASS' : 'FAIL') . "\n";

  // Case 8: NgsFileRoute::processSpecialFileTypes switches css→less/sass
  $route8a = new \ngs\routes\NgsFileRoute();
  $route8a->setFileType('css');
  $route8a->processSpecialFileTypes(['css','less','app.css']);
  $pass8a = $route8a->getFileType() === 'less';

  $route8b = new \ngs\routes\NgsFileRoute();
  $route8b->setFileType('css');
  $route8b->processSpecialFileTypes(['css','theme','sass','main.css']);
  $pass8b = $route8b->getFileType() === 'sass';

  echo "NgsFileRoute processSpecialFileTypes (less): " . ($pass8a ? 'PASS' : 'FAIL') . "\n";
  echo "NgsFileRoute processSpecialFileTypes (sass): " . ($pass8b ? 'PASS' : 'FAIL') . "\n";

  echo "[FileStreamingTest] Completed.\n";
}
